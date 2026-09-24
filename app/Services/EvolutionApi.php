<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Talks to the Evolution API bridge (Baileys) over the internal Docker
 * network. Every WhatsApp action of the app goes through here: the channel
 * sends with it, and the connection screen will ask it for QR and state.
 */
class EvolutionApi
{
    /**
     * A positive delay makes Evolution hold the message that long while
     * showing "typing…": the human cadence WhatsApp expects from a person.
     *
     * @throws ConnectionException|RequestException
     */
    /**
     * @return string|null the sent message's id, so a later quote of it can be traced back
     *
     * @throws ConnectionException|RequestException
     */
    public function sendText(string $instance, string $number, string $text, int $delayMs = 0): ?string
    {
        $payload = ['number' => $number, 'text' => $text];

        if ($delayMs > 0) {
            $payload['delay'] = $delayMs;
        }

        $id = $this->request()
            ->post("/message/sendText/{$instance}", $payload)
            ->throw()
            ->json('key.id');

        return is_string($id) ? $id : null;
    }

    /**
     * @throws ConnectionException|RequestException
     */
    public function react(string $instance, string $remoteJid, string $messageId, string $emoji): void
    {
        $this->request()
            ->post("/message/sendReaction/{$instance}", [
                'key' => ['remoteJid' => $remoteJid, 'fromMe' => false, 'id' => $messageId],
                'reaction' => $emoji,
            ])
            ->throw();
    }

    /**
     * @throws ConnectionException|RequestException
     */
    public function markRead(string $instance, string $remoteJid, string $messageId): void
    {
        $this->request()
            ->post("/chat/markMessageAsRead/{$instance}", [
                'readMessages' => [
                    ['remoteJid' => $remoteJid, 'fromMe' => false, 'id' => $messageId],
                ],
            ])
            ->throw();
    }

    /**
     * @throws ConnectionException|RequestException
     */
    public function createInstance(string $name): void
    {
        $this->request()
            ->post('/instance/create', [
                'instanceName' => $name,
                'integration' => 'WHATSAPP-BAILEYS',
                'qrcode' => true,
            ])
            ->throw();
    }

    /**
     * The current pairing QR as a data URI, or null once the instance is
     * linked (Evolution stops issuing codes). The code rotates server-side,
     * so the screen polls this instead of framing a stale image.
     *
     * @throws ConnectionException|RequestException
     */
    public function qrCode(string $instance): ?string
    {
        $base64 = (string) $this->request()
            ->get("/instance/connect/{$instance}")
            ->throw()
            ->json('base64', '');

        return $base64 === '' ? null : $base64;
    }

    /**
     * Points the instance's webhook at us. The secret travels as a header on
     * every delivery: the endpoint is unauthenticated by nature, so the header
     * is the only proof the call really comes from our Evolution.
     *
     * @param  list<string>  $events
     *
     * @throws ConnectionException|RequestException
     */
    public function registerWebhook(string $instance, string $url, array $events, string $secret): void
    {
        $this->request()
            ->post("/webhook/set/{$instance}", [
                'webhook' => [
                    'enabled' => true,
                    'url' => $url,
                    'events' => $events,
                    'headers' => ['X-Webhook-Secret' => $secret],
                    'byEvents' => false,
                    // Media (voice notes) travels base64 inside the event
                    // itself: the separate download endpoint is flaky.
                    'base64' => true,
                ],
            ])
            ->throw();
    }

    /**
     * Shows "typing…" in the customer's chat while the assistant thinks.
     * The delay is how long the bubble may live: the real reply cuts it off.
     *
     * @throws ConnectionException|RequestException
     */
    public function markComposing(string $instance, string $number, int $delaySeconds = 15): void
    {
        $this->request()
            ->post("/chat/sendPresence/{$instance}", [
                'number' => $number,
                'presence' => 'composing',
                'delay' => $delaySeconds * 1000,
            ])
            ->throw();
    }

    /**
     * @throws ConnectionException|RequestException
     */
    public function connectionState(string $instance): string
    {
        return (string) $this->request()
            ->get("/instance/connectionState/{$instance}")
            ->throw()
            ->json('instance.state', 'closed');
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.evolution.url'), '/'))
            ->withHeaders(['apikey' => (string) config('services.evolution.key')])
            ->connectTimeout(5)
            ->timeout(10);
    }
}
