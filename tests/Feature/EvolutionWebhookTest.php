<?php

declare(strict_types=1);

use App\Jobs\ProcessIncomingWhatsAppMessage;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    config()->set('services.evolution.webhook_secret', 'test-secret');
    Queue::fake();
});

/** @return array<string, mixed> */
function evolutionMessagePayload(array $overrides = []): array
{
    return array_replace_recursive([
        'event' => 'messages.upsert',
        'instance' => 'atendia-demo',
        'data' => [
            'key' => [
                'remoteJid' => '5491122334455@s.whatsapp.net',
                'fromMe' => false,
                'id' => 'MSG-1',
            ],
            'pushName' => 'Carla',
            'message' => ['conversation' => 'Hola, ¿tienen turnos mañana?'],
        ],
    ], $overrides);
}

test('a delivery without the secret header is refused', function (): void {
    $this->postJson('/api/webhooks/evolution', evolutionMessagePayload())
        ->assertUnauthorized();

    Queue::assertNothingPushed();
});

test('a delivery with a wrong secret is refused', function (): void {
    $this->postJson('/api/webhooks/evolution', evolutionMessagePayload(), ['X-Webhook-Secret' => 'guessed'])
        ->assertUnauthorized();

    Queue::assertNothingPushed();
});

test('an unconfigured secret refuses every delivery', function (): void {
    // Fail closed: an empty secret must not turn the endpoint public.
    config()->set('services.evolution.webhook_secret', '');

    $this->postJson('/api/webhooks/evolution', evolutionMessagePayload(), ['X-Webhook-Secret' => ''])
        ->assertUnauthorized();
});

test('an inbound text is parsed and queued', function (): void {
    $this->postJson('/api/webhooks/evolution', evolutionMessagePayload(), ['X-Webhook-Secret' => 'test-secret'])
        ->assertOk()
        ->assertJson(['handled' => true]);

    Queue::assertPushed(ProcessIncomingWhatsAppMessage::class, function (ProcessIncomingWhatsAppMessage $job): bool {
        return $job->instance === 'atendia-demo'
            && $job->from === '5491122334455'
            && $job->senderName === 'Carla'
            && $job->text === 'Hola, ¿tienen turnos mañana?'
            && $job->messageId === 'MSG-1';
    });
});

test('a quoted reply carries its text in extendedTextMessage', function (): void {
    $payload = evolutionMessagePayload();
    $payload['data']['message'] = ['extendedTextMessage' => ['text' => 'Sí, ese mismo']];

    $this->postJson('/api/webhooks/evolution', $payload, ['X-Webhook-Secret' => 'test-secret'])
        ->assertJson(['handled' => true]);

    Queue::assertPushed(ProcessIncomingWhatsAppMessage::class, fn (ProcessIncomingWhatsAppMessage $job): bool => $job->text === 'Sí, ese mismo');
});

test('own echoes are acknowledged but not processed', function (): void {
    // fromMe covers the assistant's replies too: processing them loops it
    // against itself.
    $payload = evolutionMessagePayload(['data' => ['key' => ['fromMe' => true]]]);

    $this->postJson('/api/webhooks/evolution', $payload, ['X-Webhook-Secret' => 'test-secret'])
        ->assertOk()
        ->assertJson(['handled' => false]);

    Queue::assertNothingPushed();
});

test('group messages are acknowledged but not processed', function (): void {
    $payload = evolutionMessagePayload(['data' => ['key' => ['remoteJid' => '1203630@g.us']]]);

    $this->postJson('/api/webhooks/evolution', $payload, ['X-Webhook-Secret' => 'test-secret'])
        ->assertJson(['handled' => false]);

    Queue::assertNothingPushed();
});

test('events other than an inbound message are acknowledged but not processed', function (): void {
    $this->postJson('/api/webhooks/evolution', ['event' => 'qrcode.updated'], ['X-Webhook-Secret' => 'test-secret'])
        ->assertOk()
        ->assertJson(['handled' => false]);

    Queue::assertNothingPushed();
});

test('a message with no text is acknowledged but not processed', function (): void {
    // Stickers, audio and images come without text for now: acknowledged so
    // Evolution stays quiet, skipped so the queue only sees real questions.
    $payload = evolutionMessagePayload();
    $payload['data']['message'] = ['stickerMessage' => ['url' => 'x']];

    $this->postJson('/api/webhooks/evolution', $payload, ['X-Webhook-Secret' => 'test-secret'])
        ->assertJson(['handled' => false]);

    Queue::assertNothingPushed();
});
