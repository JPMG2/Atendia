<?php

declare(strict_types=1);

use App\Classes\Main\EmbeddingSpace;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\KnowledgeDocument;
use App\Models\Product;
use App\Services\Knowledge\KnowledgeEmbedder;
use Database\Seeders\AssistantSkillSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TextUsage;
use Tests\Support\AiEval\StressCases;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Http::fake(['*' => Http::response(['results' => [['flagged' => false]]])]);

    $this->app->instance(KnowledgeEmbedder::class, new class extends KnowledgeEmbedder
    {
        public function embed(array $texts, ?EmbeddingSpace $space = null): array
        {
            return array_map(fn (): array => array_fill(0, 1536, 0.001), array_values($texts));
        }
    });

    $this->seed(AssistantSkillSeeder::class);
});

test('each stress case seeds the load it promises, for free', function (): void {
    [$business, $thread, $question] = StressCases::seed('thread');
    expect(ConversationMessage::query()->where('conversation_id', $thread->id)->count())->toBe(12)
        ->and($question)->toContain('número de mi pedido');

    [$business] = StressCases::seed('catalog');
    expect(Product::query()->where('business_id', $business->id)->count())->toBe(304)
        ->and(Product::query()->where('business_id', $business->id)->where('name', 'like', '%reforzada%')->count())->toBe(1);

    [$business] = StressCases::seed('document');
    $policy = KnowledgeDocument::query()->where('business_id', $business->id)->where('title', 'like', 'Política%')->sole();
    expect(mb_strlen($policy->content))->toBeGreaterThan(10_000)
        ->and(substr_count($policy->content, '17'))->toBe(1)
        ->and(KnowledgeDocument::query()->where('business_id', $business->id)->where('title', 'Garantía y cambios')->exists())->toBeFalse();

    [, , $wall] = StressCases::seed('wall');
    expect(mb_strlen($wall))->toBeGreaterThan(3_000)
        ->and($wall)->toEndWith('¿cuánto sale la caja de tornillos x100?');

    [, $stale] = StressCases::seed('stale');
    expect(Conversation::query()->whereKey($stale->id)->exists())->toBeTrue()
        ->and(ConversationMessage::query()->where('conversation_id', $stale->id)->where('body', 'like', '%3.900%')->exists())->toBeTrue();
});

test('the photo case sends one real image and judges the drill price', function (): void {
    [, , $question] = StressCases::seed('photo');
    $attachments = StressCases::attachments('photo');

    expect($question)->toContain('solo la foto')
        ->and($attachments)->toHaveCount(1)
        ->and(StressCases::attachments('thread'))->toBe([])
        ->and(StressCases::verdict('photo', 'El taladro percutor Bosch sale $89.000.'))->toBe([true, ''])
        ->and(StressCases::verdict('photo', 'Veo una caja azul con letras blancas.')[0])->toBeFalse();
});

test('the spending ceiling really counts what a call costs', function (): void {
    $response = new AgentResponse('x', 'ok', new TextUsage(inputTokens: 1_000_000, outputTokens: 1_000_000), new Meta);

    // A million tokens in and out on the cheap model: 0.10 + 0.50.
    expect(StressCases::charge($response, 'gpt-6-luna'))->toBe(0.6)
        ->and(StressCases::overCap())->toBeTrue();

    StressCases::reset();
});

test('the checks accept a right answer and reject a wrong one', function (): void {
    expect(StressCases::figureIn('Cuesta $89.000', 89000))->toBeTrue()
        ->and(StressCases::figureIn('Cuesta 89000,00 pesos', 89000))->toBeTrue()
        ->and(StressCases::figureIn('Cuesta $890.000', 89000))->toBeFalse()
        ->and(StressCases::figureIn('Son 17 días corridos', 17))->toBeTrue()
        ->and(StressCases::figureIn('Son 170 días', 17))->toBeFalse();

    $right = 'Cerramos a las 13:00 los sábados, el taladro Bosch sale $89.000 y estamos en Av. San Martín 4500.';

    expect(StressCases::verdict('three', $right))->toBe([true, ''])
        ->and(StressCases::verdict('three', 'Cerramos a las 13:00 y estamos en San Martín 4500.')[0])->toBeFalse()
        ->and(StressCases::verdict('stale', 'La caja sale $4.500.'))->toBe([true, ''])
        ->and(StressCases::verdict('stale', 'Era $3.900, ahora $4.500.')[0])->toBeFalse()
        ->and(StressCases::verdict('thread', 'Tu pedido es el 48213.'))->toBe([true, ''])
        ->and(StressCases::verdict('thread', 'No lo tengo anotado.')[0])->toBeFalse();
});
