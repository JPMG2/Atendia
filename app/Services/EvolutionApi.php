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
     * @throws ConnectionException|RequestException
     */
    public function sendText(string $instance, string $number, string $text): void
    {
        $this->request()
            ->post("/message/sendText/{$instance}", [
                'number' => $number,
                'text' => $text,
            ])
            ->throw();
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
                    'base64' => false,
                ],
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
