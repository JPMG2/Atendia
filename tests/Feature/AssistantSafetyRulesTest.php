<?php

declare(strict_types=1);

use App\Ai\Agents\AsistenteAtendia;
use App\Classes\Main\GroundedFigures;
use App\Classes\Main\HandoffSignals;
use App\Enums\ConversationStatus;
use App\Enums\HandoffLevel;
use App\Enums\HandoffSignal;
use App\Jobs\ProcessIncomingWhatsAppMessage;
use App\Models\Business;
use App\Models\Conversation;
use App\Services\Knowledge\KnowledgeEmbedder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('services.evolution.url', 'http://evolution.test');
    config()->set('services.evolution.key', 'test-key');
    config()->set('ai.providers.openai.url', 'http://openai.test/v1');
    config()->set('ai.providers.openai.key', 'test-openai');

    Cache::flush();

    $this->mock(KnowledgeEmbedder::class)
        ->shouldReceive('embedOne')
        ->andReturn(array_fill(0, 1536, 0.001))
        ->byDefault();

    Http::fake([
        'http://openai.test/*' => Http::response(['results' => [['flagged' => false]]]),
        'http://evolution.test/*' => Http::response(['status' => 'PENDING']),
    ]);
});

/** @return list<string> */
function safetySentTexts(): array
{
    return array_values(array_map(
        fn (ClientRequest $request): string => (string) $request['text'],
        array_filter(
            array_map(fn (array $pair): ClientRequest => $pair[0], Http::recorded()->all()),
            fn (ClientRequest $request): bool => str_contains($request->url(), '/message/sendText/') && $request['number'] === '5491122334455',
        ),
    ));
}

function safetyBusiness(array $overrides = []): Business
{
    return Business::factory()->create(['whatsapp_instance' => 'demo', ...$overrides]);
}

function customerWrites(string $text): void
{
    (new ProcessIncomingWhatsAppMessage('demo', '5491122334455', 'Carla', $text, 'MSG-'.fake()->unique()->numerify('####')))->handle();
}

test('the detector reads an emergency or a request for a person', function (string $text, ?HandoffSignal $expected): void {
    expect(HandoffSignals::detect($text))->toBe($expected);
})->with([
    'chest pain' => ['Tengo un dolor muy fuerte en el pecho ahora mismo', HandoffSignal::Emergency],
    'cannot breathe' => ['No puedo respirar, ayuda', HandoffSignal::Emergency],
    'self harm' => ['me quiero matar', HandoffSignal::Emergency],
    'english emergency' => ['I have chest pain', HandoffSignal::Emergency],
    'asks for a person' => ['Quiero hablar con una persona', HandoffSignal::HumanRequested],
    'asks as a question' => ['¿Puedo hablar con un asesor?', HandoffSignal::HumanRequested],
    'rejects the bot' => ['no quiero hablar con un bot', HandoffSignal::HumanRequested],
    'english request' => ['I want to talk to a human', HandoffSignal::HumanRequested],
    'portuguese request' => ['quero falar com um atendente', HandoffSignal::HumanRequested],
    'person after an unrelated no' => ['no sé, quiero hablar con una persona', HandoffSignal::HumanRequested],
]);

test('a negated or past phrase never fires and the model keeps the call', function (string $text): void {
    expect(HandoffSignals::detect($text))->toBeNull();
})->with([
    'negated request' => ['no quiero hablar con una persona'],
    'no need' => ['no hace falta que me pase con un humano'],
    'negated symptom' => ['no tengo dolor de pecho, solo una consulta'],
    'without symptom' => ['vine sin dolor en el pecho'],
    'story from the past' => ['mi papá tuvo un infarto hace 5 años y quiero saber el horario'],
    'plain question' => ['¿A qué hora abren?'],
    'person as customer' => ['soy una persona nueva, ¿cómo me anoto?'],
]);

test('an emergency hands the thread to the team without asking the model', function (): void {
    safetyBusiness();
    AsistenteAtendia::fake(['Nunca debería salir.']);

    customerWrites('Tengo un dolor muy fuerte en el pecho ahora mismo');

    $thread = Conversation::query()->sole();

    AsistenteAtendia::assertNeverPrompted();

    expect($thread->status)->toBe(ConversationStatus::Team)
        ->and($thread->messages()->count())->toBe(2)
        ->and($thread->messages()->latest('id')->first()->body)->toContain('emergencia');
});

test('a plain request for a person hands the thread over', function (): void {
    safetyBusiness(['handoff_level' => HandoffLevel::Balanced]);
    AsistenteAtendia::fake(['Nunca debería salir.']);

    customerWrites('Quiero hablar con una persona');

    AsistenteAtendia::assertNeverPrompted();

    expect(Conversation::query()->sole()->status)->toBe(ConversationStatus::Team);
});

test('a negated request leaves the answer to the model', function (): void {
    safetyBusiness();
    AsistenteAtendia::fake(['Claro, ¿en qué te ayudo?', 'Claro, ¿en qué te ayudo?']);

    customerWrites('no quiero hablar con una persona, quiero saber el precio');

    expect(Conversation::query()->sole()->status)->not->toBe(ConversationStatus::Team)
        ->and(safetySentTexts())->toContain('Claro, ¿en qué te ayudo?');
});

test('a business on minimal handoff keeps the request for the model', function (): void {
    safetyBusiness(['handoff_level' => HandoffLevel::Minimal]);
    AsistenteAtendia::fake(['Te cuento qué puedo resolver yo.', 'Te cuento qué puedo resolver yo.']);

    customerWrites('Quiero hablar con una persona');

    expect(Conversation::query()->sole()->status)->not->toBe(ConversationStatus::Team)
        ->and(safetySentTexts())->toContain('Te cuento qué puedo resolver yo.');
});

test('a price nobody gave the assistant is replaced by an honest answer', function (): void {
    safetyBusiness();
    AsistenteAtendia::fake(['El corte cuesta $45.', 'El corte cuesta $45.']);

    customerWrites('¿Cuánto sale un corte?');

    expect(safetySentTexts())->toHaveCount(1)
        ->and(safetySentTexts()[0])->toContain('no lo tengo confirmado')
        ->and(safetySentTexts()[0])->not->toContain('45');
});

test('a total built from figures the customer gave is not an invention', function (): void {
    safetyBusiness();
    AsistenteAtendia::fake(['Son $90 en total.', 'Son $90 en total.']);

    customerWrites('Quiero dos cortes de $45, ¿cuánto serían?');

    expect(safetySentTexts()[0])->toContain('$90');
});

test('figures are matched across formats and derived totals', function (): void {
    expect(GroundedFigures::missing('Cuesta $1.250,50', ['Precio: 1250.50']))->toBe([])
        ->and(GroundedFigures::missing('Son 120 USD', ['Precio unitario 40 USD', 'Cantidad 3']))->toBe([])
        ->and(GroundedFigures::missing('Cuesta $75', ['Precio 70']))->toBe(['75'])
        ->and(GroundedFigures::missing('Abrimos a las 14:00, son 300 conversaciones', []))->toBe([]);
});
