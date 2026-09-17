<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

test('the cost report shows measured usage per business, in dollars when rates are set', function (): void {
    config()->set('atendia.ai_rates.prompt_per_million', 2.0);
    config()->set('atendia.ai_rates.completion_per_million', 10.0);
    config()->set('atendia.ai_rates.audio_per_minute', 0.003);

    $business = Business::factory()->create(['name' => 'Laboratorio Vida']);
    $conversation = Conversation::factory()->create(['business_id' => $business->id]);

    ConversationMessage::factory()->for($conversation)->create([
        'business_id' => $business->id, 'audio_seconds' => 120,
    ]);
    // 1M in + 100k out at the rates above: $2 + $1 + $0.006 of audio.
    ConversationMessage::factory()->out()->for($conversation)->create([
        'business_id' => $business->id, 'prompt_tokens' => 1_000_000, 'completion_tokens' => 100_000,
    ]);

    expect(Artisan::call('atendia:ai-costs'))->toBe(0);
    $output = Artisan::output();

    expect($output)
        ->toContain('Laboratorio Vida')
        ->toContain('1,000,000')
        ->toContain('100,000')
        ->toContain('2.0')
        ->toContain('$3.01');
});

test('without rates the report still shows the tokens', function (): void {
    config()->set('atendia.ai_rates.prompt_per_million', null);
    config()->set('atendia.ai_rates.completion_per_million', null);

    $business = Business::factory()->create(['name' => 'Prueba Conexión']);
    $conversation = Conversation::factory()->create(['business_id' => $business->id]);
    ConversationMessage::factory()->out()->for($conversation)->create([
        'business_id' => $business->id, 'prompt_tokens' => 500, 'completion_tokens' => 50,
    ]);

    expect(Artisan::call('atendia:ai-costs'))->toBe(0);

    expect(Artisan::output())
        ->toContain('Prueba Conexión')
        ->toContain('configurar tarifas');
});

test('a month with no usage says so', function (): void {
    expect(Artisan::call('atendia:ai-costs', ['--month' => '2020-01']))->toBe(0);

    expect(Artisan::output())->toContain('No AI usage recorded for 2020-01');
});
