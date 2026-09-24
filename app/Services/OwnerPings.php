<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Which thread each ping to the owner was about. When the owner answers by
 * QUOTING a ping, the quote names the customer: with two handoffs open,
 * "the latest thread" sent answers to the wrong person (2026-09-24 audit).
 */
class OwnerPings
{
    private const int DAYS = 14;

    public function remember(string $instance, ?string $pingId, int $threadId): void
    {
        if ($pingId !== null && $pingId !== '') {
            Cache::put("wa:ping:{$instance}:{$pingId}", $threadId, now()->addDays(self::DAYS));
        }
    }

    public function threadFor(string $instance, ?string $pingId): ?int
    {
        $threadId = $pingId !== null && $pingId !== '' ? Cache::get("wa:ping:{$instance}:{$pingId}") : null;

        return $threadId !== null ? (int) $threadId : null;
    }
}
