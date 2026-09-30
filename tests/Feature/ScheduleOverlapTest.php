<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

test('every scheduled AtendIa command refuses to overlap itself', function (): void {
    // They walk business by business over WhatsApp and the model. Without the
    // lock, a slow tick still running when the next starts re-picks rows it has
    // not stamped yet and a customer gets the same message twice.
    $unprotected = collect(app(Schedule::class)->events())
        ->filter(fn (Event $event): bool => str_contains((string) $event->command, 'atendia:'))
        ->reject(fn (Event $event): bool => $event->withoutOverlapping)
        ->map(fn (Event $event): string => (string) $event->command)
        ->values()
        ->all();

    expect($unprotected)->toBe([]);
});
