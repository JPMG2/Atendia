<?php

declare(strict_types=1);

use App\Ai\Agents\MessageTriage;
use App\Models\AiUsage;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Services\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Ai\AiManager;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The usage meter — every AI call, per client, at its real size
|--------------------------------------------------------------------------
| One SDK listener records each call under the business it ran for, cached
| input apart (it is cheaper); the report adds each client's volume.
*/

function promptedEvent(Usage $usage): AgentPrompted
{
    $agent = new MessageTriage;

    return new AgentPrompted('inv-1', new AgentPrompt($agent, 'hola', [], app(AiManager::class)->textProvider('openai'), 'gpt-6-astra'), new AgentResponse('inv-1', '', $usage, new Meta('openai', 'gpt-6-astra')));
}

test('every agent call is metered under its business, cached input apart', function (): void {
    $business = Business::factory()->create();

    app(Tenant::class)->for($business->id, fn () => event(promptedEvent(new Usage(promptTokens: 9, completionTokens: 40, cacheWriteInputTokens: 1, cacheReadInputTokens: 2_000))));

    $row = AiUsage::query()->sole();

    expect($row->business_id)->toBe($business->id)
        ->and($row->kind)->toBe('MessageTriage')
        ->and($row->model)->toBe('gpt-6-astra')
        ->and($row->input_tokens)->toBe(10)
        ->and($row->cached_tokens)->toBe(2_000)
        ->and($row->output_tokens)->toBe(40);
});

test('the report shows each client volume and real cost, split by kind', function (): void {
    config()->set('atendia.ai_rates.prompt_per_million', 2.0);
    config()->set('atendia.ai_rates.cached_per_million', 0.5);
    config()->set('atendia.ai_rates.completion_per_million', 10.0);
    config()->set('atendia.ai_rates.embedding_per_million', 0.02);
    config()->set('atendia.ai_rates.audio_per_minute', 0.003);

    $business = Business::factory()->create(['name' => 'Laboratorio Vida']);
    $conversation = Conversation::factory()->create(['business_id' => $business->id]);
    ConversationMessage::factory()->for($conversation)->create(['business_id' => $business->id, 'audio_seconds' => 120]);
    ConversationMessage::factory()->out()->for($conversation)->create(['business_id' => $business->id]);

    // $2 in + $1 cached + $1 out + $0.02 embeddings + $0.006 audio.
    AiUsage::query()->create(['business_id' => $business->id, 'kind' => 'AsistenteAtendia', 'input_tokens' => 1_000_000, 'cached_tokens' => 2_000_000, 'output_tokens' => 100_000]);
    AiUsage::query()->create(['business_id' => $business->id, 'kind' => AiUsage::EMBEDDINGS, 'input_tokens' => 1_000_000]);

    expect(Artisan::call('atendia:ai-costs'))->toBe(0);

    expect(Artisan::output())
        ->toContain('Laboratorio Vida')
        ->toContain('2,000,000')
        ->toContain('2.0')
        ->toContain('$4.03')
        ->toContain('AsistenteAtendia')
        ->toContain('$4.00')
        ->toContain('embeddings')
        ->toContain('$0.02');
});

test('without rates the report still shows the tokens', function (): void {
    config()->set('atendia.ai_rates.prompt_per_million', null);

    $business = Business::factory()->create(['name' => 'Prueba Conexión']);
    AiUsage::query()->create(['business_id' => $business->id, 'kind' => 'AsistenteAtendia', 'input_tokens' => 500, 'output_tokens' => 50]);

    expect(Artisan::call('atendia:ai-costs'))->toBe(0);

    expect(Artisan::output())
        ->toContain('Prueba Conexión')
        ->toContain('configurar tarifas');
});

test('a month with no usage says so', function (): void {
    expect(Artisan::call('atendia:ai-costs', ['--month' => '2020-01']))->toBe(0);

    expect(Artisan::output())->toContain('No AI usage recorded for 2020-01');
});
