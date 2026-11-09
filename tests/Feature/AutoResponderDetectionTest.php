<?php

declare(strict_types=1);

use App\Ai\Agents\AsistenteAtendia;
use App\Enums\AutoReplySignal;
use App\Enums\ConversationStatus;
use App\Enums\MessageAuthor;
use App\Enums\MessageDirection;
use App\Jobs\ProcessIncomingWhatsAppMessage;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\PlatformSensor;
use App\Models\User;
use App\Services\AutoResponderDetector;
use App\Services\Knowledge\KnowledgeEmbedder;
use App\Services\PlatformSensors;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

const BOT_PHONE = '5491177778888';

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

    // Each answer is two canned passes (the fake re-asks once); the second one is sent.
    AsistenteAtendia::fake(collect(range(1, 12))
        ->flatMap(fn (int $lap): array => ['Borrador.', "Respuesta número {$lap} del asistente, con el detalle que pediste."])
        ->all());

    Business::factory()->create(['whatsapp_instance' => 'bots-demo']);
});

/** One inbound from the other side, heard by the webhook at $arrivedAtMs (now when null). */
function botLap(string $text, int $lap, ?int $arrivedAtMs = null): void
{
    (new ProcessIncomingWhatsAppMessage('bots-demo', BOT_PHONE, 'Ferretería', $text, "BOT-{$lap}", arrivedAtMs: $arrivedAtMs))->handle();
}

function botThread(): Conversation
{
    return Conversation::query()->where('contact_phone', BOT_PHONE)->sole();
}

/** @return list<Request> */
function botRepliesSent(): array
{
    return array_values(array_filter(
        array_map(fn (array $pair): Request => $pair[0], Http::recorded()->all()),
        fn (Request $request): bool => str_contains($request->url(), '/message/sendText/') && $request['number'] === BOT_PHONE,
    ));
}

test('two bots answering each other are cut on the third lap, in silence', function (): void {
    // The other side answers our every reply at once, with the same canned line.
    foreach (range(1, 5) as $lap) {
        botLap('Hola, ¿en qué podemos ayudarte hoy?', $lap);

        if (botThread()->status === ConversationStatus::Team) {
            break;
        }
    }

    $thread = botThread();

    expect($lap)->toBe(3)
        ->and(botRepliesSent())->toHaveCount(2)
        ->and($thread->status)->toBe(ConversationStatus::Team)
        ->and($thread->bot_suspected_at)->not->toBeNull()
        // The lap that tripped is still on record for the team to read.
        ->and($thread->messages()->where('direction', MessageDirection::In)->count())->toBe(3);
});

test('a canned auto-reply phrase stops the assistant on the first message', function (): void {
    botLap('¡Gracias por comunicarte con Ferretería Sur! En breve te responderemos.', 1);

    expect(botRepliesSent())->toBe([])
        ->and(botThread()->status)->toBe(ConversationStatus::Team)
        ->and(botThread()->bot_suspected_at)->not->toBeNull();
});

test('our own reply coming back is an echo and stops the loop', function (): void {
    botLap('Quiero saber el horario', 1, now()->getTimestampMs() + 60_000);

    botLap('Respuesta número 1 del asistente, con el detalle que pediste.', 2, now()->getTimestampMs() + 120_000);

    expect(botRepliesSent())->toHaveCount(1)
        ->and(botThread()->bot_suspected_at)->not->toBeNull();
});

test('a person writing at human pace is never taken for a machine', function (): void {
    $texts = ['Hola', '¿Tienen tornillos de 6 mm?', '¿Y cuánto salen?', 'Perfecto, paso mañana', 'Gracias'];

    foreach ($texts as $index => $text) {
        botLap($text, $index + 1, now()->getTimestampMs() + ($index + 1) * 30_000);
    }

    expect(botRepliesSent())->toHaveCount(5)
        ->and(botThread()->status)->toBe(ConversationStatus::Open)
        ->and(botThread()->bot_suspected_at)->toBeNull();
});

test('the assistant stops at the reply cap for one thread even with no other signal', function (): void {
    botLap('Hola', 1, now()->getTimestampMs() + 60_000);

    foreach (range(1, 14) as $n) {
        botThread()->messages()->create([
            'business_id' => botThread()->business_id,
            'direction' => MessageDirection::Out,
            'author' => MessageAuthor::Assistant,
            'body' => "Respuesta previa {$n}",
        ]);
    }

    botLap('¿Y los martes?', 2, now()->getTimestampMs() + 120_000);

    expect(botRepliesSent())->toHaveCount(1)
        ->and(botThread()->bot_suspected_at)->not->toBeNull();
});

test('the panel marks the thread and handing it back clears the mark', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    $user->business()->associate(Business::query()->sole())->save();

    $thread = Conversation::factory()->create([
        'business_id' => $user->business_id,
        'contact_phone' => BOT_PHONE,
        'status' => ConversationStatus::Team,
        'bot_suspected_at' => now(),
        'bot_signal' => AutoReplySignal::Repeated,
    ]);
    $this->actingAs($user);

    livewire('conversations.index')
        ->call('open', $thread->id)
        ->assertSee(__('client.conversations.bot_suspected'))
        // The tell rides on hover, so she can judge the call before undoing it.
        ->assertSee(__('client.conversations.bot_signal.repeated'))
        ->assertSee(__('client.conversations.resume_person'))
        ->call('resume');

    expect($thread->refresh()->status)->toBe(ConversationStatus::Open)
        ->and($thread->bot_suspected_at)->toBeNull()
        ->and(Cache::has('wa:person:bots-demo:'.BOT_PHONE))->toBeTrue();
});

test('after "it was a person" the same number is answered even with the bot-like text again', function (): void {
    botLap('Hola, ¿en qué podemos ayudarte hoy?', 1, now()->getTimestampMs() + 60_000);
    botLap('Hola, ¿en qué podemos ayudarte hoy?', 2, now()->getTimestampMs() + 120_000);
    botLap('Hola, ¿en qué podemos ayudarte hoy?', 3, now()->getTimestampMs() + 180_000);

    expect(botThread()->bot_suspected_at)->not->toBeNull();

    AutoResponderDetector::trustAsPerson('bots-demo:'.BOT_PHONE);
    botThread()->update(['status' => ConversationStatus::Open]);

    botLap('Hola, ¿en qué podemos ayudarte hoy?', 4, now()->getTimestampMs() + 240_000);

    expect(botRepliesSent())->toHaveCount(3)
        ->and(botThread()->status)->toBe(ConversationStatus::Open);
});

test('everyday human replies are never read as a machine', function (string ...$texts): void {
    foreach ($texts as $index => $text) {
        // Short answers sent fast on purpose: speed alone never convicts a short line.
        botLap($text, $index + 1);
    }

    expect(botRepliesSent())->toHaveCount(count($texts))
        ->and(botThread()->bot_suspected_at)->toBeNull();
})->with([
    'yes three times' => ['¿A qué hora abren?', 'Sí', 'Sí', 'Sí'],
    'quick oks' => ['Quiero una cita', 'ok', 'ok', 'dale'],
    'a confirmation quoting our reply' => ['¿Hay turno?', 'Respuesta número 1 del asistente'],
    'thanks to the business' => ['Gracias por comunicarse conmigo, ahí estaré'],
    'asking if it is automatic' => ['¿Esto es una respuesta automática o me atiende alguien?'],
]);

test('closing the thread anywhere clears the mark, not only from the panel', function (): void {
    botLap('¡Gracias por comunicarte con Ferretería Sur! En breve te responderemos.', 1);

    expect(botThread()->bot_signal)->toBe(AutoReplySignal::Phrase);

    // "#resuelto" from the owner's WhatsApp and the idle close both just save the status.
    botThread()->update(['status' => ConversationStatus::Resolved]);

    expect(botThread()->bot_suspected_at)->toBeNull()
        ->and(botThread()->bot_signal)->toBeNull();
});

test('the admin board counts this week\'s silenced chats and leads to the knobs', function (): void {
    botLap('¡Gracias por comunicarte con Ferretería Sur! En breve te responderemos.', 1);

    $reading = collect(app(PlatformSensors::class)->read())->firstWhere('key', 'auto_responders');

    expect($reading->value)->toBe('1')
        ->and((new PlatformSensor)->forceFill(['sensor_key' => 'auto_responders'])->target()['route'])->toBe('admin.settings');
});
