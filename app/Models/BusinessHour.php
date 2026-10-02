<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToBusiness;
use Database\Factories\BusinessHourFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * One opening shift of a business: a day can hold several rows (morning and
 * afternoon shifts). Times are local to the business's timezone.
 *
 * `business_id` stays out of Fillable on purpose: it is the tenant boundary
 * and is only ever set through the relation, like {@see Service}.
 */
#[Fillable(['day_of_week', 'opens_at', 'closes_at'])]
class BusinessHour extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<BusinessHourFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
        ];
    }

    /**
     * A shift that closes at or before it opens runs past midnight: it ends
     * on the NEXT day. One place decides it, because the screen, the clock
     * and the agenda all have to agree on what 22:00–02:00 means.
     */
    public static function crossesMidnight(string $opensAt, string $closesAt): bool
    {
        return self::minutes($closesAt) <= self::minutes($opensAt);
    }

    /**
     * The shift as minutes from the day's midnight, with the end pushed past
     * 24 h when it wraps — so two shifts can be compared as plain ranges.
     *
     * @return array{int, int}
     */
    public static function span(string $opensAt, string $closesAt): array
    {
        $opens = self::minutes($opensAt);
        $closes = self::minutes($closesAt);

        return [$opens, $closes <= $opens ? $closes + 1440 : $closes];
    }

    /**
     * The shift as the pieces of a single day it really occupies: a night
     * shift is two of them, the tail of today and the head of tomorrow. Two
     * shifts collide when any of their pieces do.
     *
     * @return list<array{int, int}>
     */
    public static function pieces(string $opensAt, string $closesAt): array
    {
        $opens = self::minutes($opensAt);
        $closes = self::minutes($closesAt);

        if ($closes > $opens) {
            return [[$opens, $closes]];
        }

        return [[$opens, 1440], [0, $closes]];
    }

    public static function minutes(string $time): int
    {
        [$hours, $minutes] = array_pad(explode(':', $time), 2, '0');

        return ((int) $hours) * 60 + (int) $minutes;
    }

    /**
     * This row's own span, for the clock and the agenda.
     *
     * @return array{int, int}
     */
    public function spanInMinutes(): array
    {
        return self::span(
            substr((string) $this->opens_at, 0, 5),
            substr((string) $this->closes_at, 0, 5),
        );
    }

    /** Whether this row runs past midnight into the next day. */
    public function runsPastMidnight(): bool
    {
        return self::crossesMidnight(
            substr((string) $this->opens_at, 0, 5),
            substr((string) $this->closes_at, 0, 5),
        );
    }

    /**
     * Localized day names keyed by day-of-week, 0 = Sunday like date("w").
     *
     * Carbon translates them for the active locale: a hand-written list in
     * lang would dodge the translator and die on the next regional variant.
     *
     * @return array<int, string>
     */
    public static function dayNames(): array
    {
        // 2024-09-01 fell on a Sunday, so day-of-week maps straight onto it.
        return collect(range(0, 6))
            ->mapWithKeys(fn (int $day): array => [
                $day => Str::ucfirst(Carbon::create(2024, 9, $day + 1)->locale(app()->getLocale())->dayName),
            ])
            ->all();
    }
}
