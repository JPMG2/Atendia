<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Hands push messages to Expo's relay, which forwards them to Google's FCM
 * with the key stored in EAS. Never retried: the relay has no idempotency key,
 * and a second attempt after a lost answer would buzz the phone twice.
 */
class ExpoPush
{
    /** Expo accepts at most 100 messages per request. */
    public const BATCH = 100;

    /**
     * One request. The caller chunks, so it knows what a failed batch cost.
     *
     * @param  list<array<string, mixed>>  $messages  Up to BATCH, each with its `to` token.
     * @return list<string> The tokens Expo says no longer exist.
     */
    public function send(array $messages): array
    {
        $tickets = Http::acceptJson()
            ->connectTimeout(3)
            ->timeout(10)
            ->post((string) config('atendia.app.push.url'), $messages)
            ->throw()
            ->json('data', []);

        $dead = [];

        // Tickets come back in the order of the batch.
        foreach ($tickets as $position => $ticket) {
            if (($ticket['details']['error'] ?? null) === 'DeviceNotRegistered') {
                $dead[] = (string) $messages[$position]['to'];
            }
        }

        return $dead;
    }
}
