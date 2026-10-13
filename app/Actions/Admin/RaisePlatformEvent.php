<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\PlatformEventKind;
use App\Enums\PlatformEventState;
use App\Models\PlatformEvent;
use Throwable;

/**
 * Writes one fact on the platform's feed, or counts it again.
 *
 * The dedupe key is the idea: ten repeats are ONE line that says ten, the way
 * an on-call tool groups its alerts. It never throws: the feed records what
 * happened, so it must not undo the suspension it was reporting.
 */
class RaisePlatformEvent
{
    public function handle(PlatformEventKind $kind, ?int $businessId = null, ?string $detail = null, ?string $dedupeKey = null): ?PlatformEvent
    {
        try {
            $key = $dedupeKey ?? $kind->value.':'.($businessId ?? 'platform');
            $now = now();

            $open = PlatformEvent::query()
                ->where('dedupe_key', $key)
                ->where('state', '!=', PlatformEventState::Resolved->value)
                ->first();

            if ($open !== null) {
                $open->forceFill([
                    'occurrences' => $open->occurrences + 1,
                    'last_seen_at' => $now,
                    'detail' => $detail ?? $open->detail,
                ])->save();

                return $open;
            }

            return PlatformEvent::query()->create([
                'kind' => $kind,
                'severity' => $kind->severity(),
                'state' => PlatformEventState::Open,
                'about_business_id' => $businessId,
                'dedupe_key' => $key,
                'detail' => $detail === null ? null : mb_substr(trim($detail), 0, 255),
                'occurrences' => 1,
                'first_seen_at' => $now,
                'last_seen_at' => $now,
            ]);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}
