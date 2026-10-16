<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\PlatformEventKind;
use App\Enums\PlatformEventState;
use App\Models\PlatformEvent;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * Closes what the code can see ended: the number answers again, the debt was
 * paid, the suspension was lifted. A condition that cleared must clear itself,
 * or the board fills with problems that were solved last week.
 *
 * Never throws, for the same reason raising never does.
 */
class ResolvePlatformEvent
{
    /** Every active line of this kind about this business (or about the platform). */
    public function ofKind(PlatformEventKind $kind, ?int $businessId = null): int
    {
        return $this->close(fn ($query) => $query
            ->where('kind', $kind->value)
            ->when($businessId === null, fn ($q) => $q->whereNull('about_business_id'), fn ($q) => $q->where('about_business_id', $businessId)));
    }

    /** Every active line about this business, whatever its kind: it is gone, so nothing about it is waiting. */
    public function ofBusiness(int $businessId): int
    {
        return $this->close(fn ($query) => $query->where('about_business_id', $businessId));
    }

    /** One specific line, by the key it was raised with. */
    public function byKey(string $dedupeKey): int
    {
        return $this->close(fn ($query) => $query->where('dedupe_key', $dedupeKey));
    }

    /** @param  callable(Builder<PlatformEvent>): mixed  $scope */
    private function close(callable $scope): int
    {
        try {
            $query = PlatformEvent::query()->active();
            $scope($query);

            return $query->update([
                'state' => PlatformEventState::Resolved->value,
                'resolved_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);

            return 0;
        }
    }
}
