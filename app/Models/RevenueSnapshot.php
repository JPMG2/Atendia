<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * What the platform was earning on a given month.
 *
 * Platform accounting, so it carries no `business_id` and no tenant scope:
 * it is the one table that is ABOUT every business at once.
 */
#[Fillable(['month', 'mrr', 'paying', 'trialing', 'gained', 'lost'])]
class RevenueSnapshot extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'month' => 'date',
            'mrr' => 'decimal:2',
            'gained' => 'decimal:2',
            'lost' => 'decimal:2',
        ];
    }

    /**
     * Takes today's photo, overwriting the month if it was taken already.
     *
     * Re-taking corrects instead of duplicating: run twice in a day it must
     * leave one row, and a month closed with a correction has to be able to
     * say the corrected figure.
     */
    public static function capture(?CarbonImmutable $month = null): self
    {
        $month = ($month ?? CarbonImmutable::now())->startOfMonth();
        $mrr = Subscription::monthlyRecurringRevenue();
        $previous = self::query()->where('month', '<', $month)->orderByDesc('month')->first();
        $change = $mrr - (float) ($previous->mrr ?? 0);

        return self::query()->updateOrCreate(
            ['month' => $month->toDateString()],
            [
                'mrr' => $mrr,
                'paying' => Subscription::payingCount(),
                'trialing' => Subscription::trialingCount(),
                // Against the previous photo, because that is the only other
                // figure that exists: without one, the first month is all gain.
                'gained' => max($change, 0),
                'lost' => abs(min($change, 0)),
            ],
        );
    }

    /**
     * The last months, oldest first, for a line that can be read left to right.
     *
     * @return Collection<int, self>
     */
    public static function recent(int $months = 12): Collection
    {
        return self::query()
            ->orderByDesc('month')
            ->limit($months)
            ->get()
            ->sortBy('month')
            ->values();
    }

    /**
     * What the month earned, as it was photographed then. Null when no photo
     * exists: a month nobody measured is not a month that earned nothing.
     */
    public static function mrrOf(CarbonInterface $month): ?float
    {
        $mrr = self::query()->where('month', $month->copy()->startOfMonth()->toDateString())->value('mrr');

        return $mrr === null ? null : (float) $mrr;
    }

    /**
     * The month before the one given, which is what a comparison needs.
     * Null when there is none: a first month compared against zero would
     * read as infinite growth.
     */
    public static function before(CarbonImmutable $month): ?self
    {
        return self::query()
            ->where('month', '<', $month->startOfMonth()->toDateString())
            ->orderByDesc('month')
            ->first();
    }
}
