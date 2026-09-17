<?php

declare(strict_types=1);

use App\Ai\Agents\AsistenteAtendia;
use App\Enums\MessageDirection;
use App\Jobs\ProcessIncomingWhatsAppMessage;
use App\Models\Business;
use App\Models\Conversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Transcription;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('services.evolution.url', 'http://evolution.test');
    config()->set('services.evolution.key', 'test-key');
    config()->set('ai.providers.openai.url', 'http://openai.test/v1');
    config()->set('ai.providers.openai.key', 'test-openai');

    Cache::flush();
});

/** @param  bool  $flagged  what the faked moderation endpoint answers */
function fakeWhatsAppHttp(bool $flagged = false): void
{
    Http::fake([
        'http://openai.test/*' => Http::response(['results' => [['flagged' => $flagged]]]),
        'http://evolution.test/*' => Http::response(['status' => 'PENDING']),
    ]);
}

function runIncoming(string $text = 'Hola', string $messageId = 'MSG-1', ?string $audio = null): void
{
    (new ProcessIncomingWhatsAppMessage('atendia-demo', '5491122334455', 'Carla', $text, $messageId, $audio))->handle();
}

/** @return list<Request> */
function sentTexts(): array
{
    return array_values(array_filter(
        array_map(fn (array $pair): Request => $pair[0], Http::recorded()->all()),
        fn (Request $request): bool => str_contains($request->url(), '/message/sendText/'),
    ));
}

test('an inbound text is answered by the assistant through the business instance', function (): void {
    fakeWhatsAppHttp();
    Business::factory()->create(['whatsapp_instance' => 'atendia-demo']);

    // Two canned turns: the fake carries no tool calls, so answer() always
    // re-asks once and the customer receives the second, grounded pass.
    AsistenteAtendia::fake(['Creo que sí.', 'Sí, mañana tenemos turnos desde las 9.']);

    runIncoming('¿Tienen turnos mañana?');

    expect(sentTexts())->toHaveCount(1)
        ->and(sentTexts()[0]['text'])->toBe('Sí, mañana tenemos turnos desde las 9.')
        ->and(sentTexts()[0]['number'])->toBe('5491122334455')
        ->and(sentTexts()[0]['delay'])->toBeGreaterThan(0);
});

test('a burst of texts collapses into one prompt and one reply', function (): void {
    fakeWhatsAppHttp();
    Business::factory()->create(['whatsapp_instance' => 'atendia-demo']);
    AsistenteAtendia::fake(['…', 'Sí, tenemos turnos.']);

    Cache::put('wa:buf:atendia-demo:5491122334455', ['Hola', '¿Están?', '¿Tienen turnos?'], 60);
    Cache::put('wa:last:atendia-demo:5491122334455', 'MSG-3', 60);

    runIncoming('¿Tienen turnos?', 'MSG-3');

    AsistenteAtendia::assertPrompted(fn ($prompt): bool => str_contains($prompt->prompt, 'Hola')
        && str_contains($prompt->prompt, '¿Están?')
        && str_contains($prompt->prompt, '¿Tienen turnos?'));

    expect(sentTexts())->toHaveCount(1);
});

test('a job outrun by a newer message dies silently instead of double-replying', function (): void {
    fakeWhatsAppHttp();
    Business::factory()->create(['whatsapp_instance' => 'atendia-demo']);
    AsistenteAtendia::fake(['nunca']);

    // The debounce was re-armed: MSG-2's job owns the burst now.
    Cache::put('wa:last:atendia-demo:5491122334455', 'MSG-2', 60);

    runIncoming('Hola', 'MSG-1');

    AsistenteAtendia::assertNeverPrompted();
    expect(sentTexts())->toBeEmpty();
});

test('the customer message gets a thumbs up and is marked as read before the reply', function (): void {
    fakeWhatsAppHttp();
    Business::factory()->create(['whatsapp_instance' => 'atendia-demo']);
    AsistenteAtendia::fake(['…', 'Listo.']);

    runIncoming('Hola', 'MSG-7');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/message/sendReaction/atendia-demo')
        && $request['reaction'] === '👍'
        && $request['key']['id'] === 'MSG-7');
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/chat/markMessageAsRead/atendia-demo')
        && $request['readMessages'][0]['id'] === 'MSG-7');
});

test('the exchange is persisted as a thread the assistant will remember', function (): void {
    fakeWhatsAppHttp();
    $business = Business::factory()->create(['whatsapp_instance' => 'atendia-demo']);
    AsistenteAtendia::fake(['…', 'Sí, mañana a las 9.']);

    runIncoming('¿Tienen turnos?');

    $conversation = Conversation::query()->sole();

    expect($conversation->business_id)->toBe($business->id)
        ->and($conversation->contact_phone)->toBe('5491122334455')
        ->and($conversation->contact_name)->toBe('Carla')
        ->and($conversation->last_message_at)->not->toBeNull();

    $turns = $conversation->messages()->orderBy('id')->get();

    expect($turns)->toHaveCount(2)
        ->and($turns[0]->direction)->toBe(MessageDirection::In)
        ->and($turns[0]->body)->toBe('¿Tienen turnos?')
        ->and($turns[0]->wa_message_id)->toBe('MSG-1')
        ->and($turns[1]->direction)->toBe(MessageDirection::Out)
        ->and($turns[1]->body)->toBe('Sí, mañana a las 9.');
});

test('a second message from the same contact grows the same thread', function (): void {
    fakeWhatsAppHttp();
    Business::factory()->create(['whatsapp_instance' => 'atendia-demo']);
    AsistenteAtendia::fake(['…', 'Sí.', '…', 'De 8 a 12.']);

    runIncoming('¿Tienen turnos?', 'MSG-1');
    runIncoming('¿En qué horario?', 'MSG-2');

    expect(Conversation::query()->count())->toBe(1)
        ->and(Conversation::query()->sole()->messages()->count())->toBe(4);
});

test('an answered exchange is tallied for the owner digest', function (): void {
    fakeWhatsAppHttp();
    $business = Business::factory()->create(['whatsapp_instance' => 'atendia-demo']);
    AsistenteAtendia::fake(['…', 'Sí, tenemos turnos.']);

    runIncoming('¿Tienen turnos?');

    $entries = Cache::get('wa:digest:'.$business->id.':'.now()->format('Y-m-d'));

    expect($entries)->toHaveCount(1)
        ->and($entries[0]['q'])->toBe('¿Tienen turnos?')
        ->and($entries[0]['a'])->toBe('Sí, tenemos turnos.')
        ->and($entries[0]['from'])->toBe('5491122334455');
});

test('a long reply leaves in short bubbles, each with a human delay', function (): void {
    fakeWhatsAppHttp();
    Business::factory()->create(['whatsapp_instance' => 'atendia-demo']);

    $long = implode("\n\n", [
        str_repeat('Los análisis de sangre se hacen de lunes a viernes. ', 5),
        str_repeat('Para el perfil tiroideo necesitás ayuno de 8 horas. ', 5),
    ]);
    AsistenteAtendia::fake(['…', $long]);

    runIncoming('¿Qué análisis hacen?');

    $texts = sentTexts();

    expect(count($texts))->toBeGreaterThanOrEqual(2);

    foreach ($texts as $request) {
        expect(mb_strlen((string) $request['text']))->toBeLessThanOrEqual(320)
            ->and($request['delay'])->toBeGreaterThan(0)
            ->and($request['delay'])->toBeLessThanOrEqual(8000);
    }
});

test('a muted sender is ignored without spending a token', function (): void {
    fakeWhatsAppHttp();
    Business::factory()->create(['whatsapp_instance' => 'atendia-demo']);
    AsistenteAtendia::fake(['nunca']);

    Cache::put('wa:mute:atendia-demo:5491122334455', true, 600);

    runIncoming();

    AsistenteAtendia::assertNeverPrompted();
    expect(sentTexts())->toBeEmpty();
});

test('a flood hits the cooldown ladder with a fixed reply, no model call', function (): void {
    fakeWhatsAppHttp();
    Business::factory()->create(['whatsapp_instance' => 'atendia-demo']);
    AsistenteAtendia::fake(['nunca']);

    Cache::put('wa:count:atendia-demo:5491122334455', 30, 3600);

    runIncoming();

    AsistenteAtendia::assertNeverPrompted();
    expect(sentTexts())->toHaveCount(1)
        ->and(sentTexts()[0]['text'])->toBe(__('assistant.guard.too_many'))
        ->and(Cache::has('wa:mute:atendia-demo:5491122334455'))->toBeTrue();
});

test('offensive content gets a fixed nudge first and the mute on repeat', function (): void {
    fakeWhatsAppHttp(flagged: true);
    Business::factory()->create(['whatsapp_instance' => 'atendia-demo']);
    AsistenteAtendia::fake(['nunca']);

    runIncoming('***', 'MSG-1');

    AsistenteAtendia::assertNeverPrompted();
    expect(sentTexts()[0]['text'])->toBe(__('assistant.guard.offensive'))
        ->and(Cache::has('wa:mute:atendia-demo:5491122334455'))->toBeFalse();

    runIncoming('***', 'MSG-2');

    expect(Cache::has('wa:mute:atendia-demo:5491122334455'))->toBeTrue();
});

test('a voice note is transcribed and answered as text', function (): void {
    fakeWhatsAppHttp();
    Business::factory()->create(['whatsapp_instance' => 'atendia-demo']);
    AsistenteAtendia::fake(['…', 'Sí, atendemos mañana.']);
    Transcription::fake(['¿Atienden mañana?']);

    runIncoming('', 'MSG-9', base64_encode('opus-bytes'));

    AsistenteAtendia::assertPrompted(fn ($prompt): bool => str_contains($prompt->prompt, '¿Atienden mañana?'));
    expect(sentTexts())->toHaveCount(1)
        ->and(sentTexts()[0]['text'])->toBe('Sí, atendemos mañana.');
});

test('a message for an unclaimed instance warns and answers nobody', function (): void {
    fakeWhatsAppHttp();
    Log::spy();

    (new ProcessIncomingWhatsAppMessage('ghost-instance', '5491122334455', 'Carla', 'Hola', 'MSG-1'))->handle();

    expect(sentTexts())->toBeEmpty();
    Log::shouldHaveReceived('warning')->withArgs(
        fn (string $message): bool => $message === 'whatsapp.incoming.unclaimed',
    )->once();
});
