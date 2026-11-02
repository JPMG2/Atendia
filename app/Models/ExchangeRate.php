<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExchangeRateSource;
use Carbon\CarbonImmutable;
use Database\Factories\ExchangeRateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * One value date of the bolivar rate. The official one charges; the parallel
 * is kept beside it only so she can see how far apart they are.
 */
#[Fillable(['currency', 'rate_date', 'official', 'source', 'parallel', 'confirmed_at', 'entered_by'])]
class ExchangeRate extends Model
{
    /** @use HasFactory<ExchangeRateFactory> */
    use HasFactory;

    public const string CURRENCY = 'VES';

    /**
     * The BCV's value date is a Venezuelan calendar day whatever the
     * platform's clock says: read on UTC, tomorrow's rate would charge from
     * 20:00 in Caracas.
     */
    public const string TIMEZONE = 'America/Caracas';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'rate_date' => 'immutable_date',
            'official' => 'decimal:4',
            'parallel' => 'decimal:4',
            'source' => ExchangeRateSource::class,
            'confirmed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    /**
     * Rates that may charge: confirmed, and already in force on the platform's
     * calendar. The BCV publishes tomorrow's rate this afternoon; it must not
     * charge before its date.
     *
     * @param  Builder<ExchangeRate>  $query
     */
    public function scopeInForce(Builder $query): void
    {
        $query->where('currency', self::CURRENCY)
            ->whereNotNull('confirmed_at')
            ->whereDate('rate_date', '<=', self::today()->toDateString());
    }

    /** The rate a payment made right now is charged with, or null when none was ever stored. */
    public static function charging(): ?self
    {
        return self::query()->inForce()->latest('rate_date')->first();
    }

    /** The newest value date stored, confirmed or not, published in advance or not. */
    public static function newest(): ?self
    {
        return self::query()->where('currency', self::CURRENCY)->latest('rate_date')->first();
    }

    /**
     * A value that jumped too far and waits for her. Only one newer than the
     * rate charging counts: an older one was overtaken by a later value date
     * and confirming it would change nothing.
     */
    public static function awaitingConfirmation(): ?self
    {
        $charging = self::charging();

        return self::query()
            ->where('currency', self::CURRENCY)
            ->whereNull('confirmed_at')
            ->when($charging, fn (Builder $query): Builder => $query->whereDate('rate_date', '>', $charging->rate_date->toDateString()))
            ->oldest('rate_date')
            ->first();
    }

    /** Whether a value stays within the jump she allows from the reference; with no reference it does. */
    public static function isWithinJump(float $value, ?float $reference): bool
    {
        if ($reference === null || $reference <= 0) {
            return true;
        }

        return self::jumpPercent($value, $reference) <= (float) config('atendia.exchange_rate.max_jump_percent');
    }

    public static function jumpPercent(float $value, float $reference): float
    {
        return $reference <= 0 ? 0.0 : abs($value / $reference - 1) * 100;
    }

    public static function forDate(CarbonImmutable $date): ?self
    {
        return self::query()->where('currency', self::CURRENCY)->whereDate('rate_date', $date->toDateString())->first();
    }

    /**
     * The newest value dates first, with who typed them.
     *
     * @return Collection<int, ExchangeRate>
     */
    public static function history(int $limit): Collection
    {
        return self::query()->where('currency', self::CURRENCY)->with('enteredBy')->latest('rate_date')->limit($limit)->get();
    }

    /**
     * The rates that charged over the last days, oldest first: the shape of
     * the month, with the parallel beside each one when it was kept.
     *
     * @return Collection<int, ExchangeRate>
     */
    public static function lastDays(int $days): Collection
    {
        return self::query()
            ->inForce()
            ->whereDate('rate_date', '>=', self::today()->subDays($days)->toDateString())
            ->oldest('rate_date')
            ->get();
    }

    /** The last confirmed official value before the given date: what a new one is compared against. */
    public static function previousOfficial(CarbonImmutable $before): ?float
    {
        $value = self::query()
            ->where('currency', self::CURRENCY)
            ->whereNotNull('confirmed_at')
            ->whereDate('rate_date', '<', $before->toDateString())
            ->latest('rate_date')
            ->value('official');

        return $value === null ? null : (float) $value;
    }

    /** Today in Venezuela, where the BCV's value date lives. */
    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(self::TIMEZONE)->startOfDay();
    }

    /**
     * Whether the rate in force is older than she allows: business days (Monday
     * to Friday) counted from its value date to today. A Friday rate on a
     * Monday is one day old, not three.
     */
    public function isStale(int $businessDays): bool
    {
        $today = self::today();
        $date = CarbonImmutable::parse($this->rate_date->toDateString(), self::TIMEZONE);

        return $date->diffInWeekdays($today) > $businessDays;
    }

    /** Percent the parallel sits above the official one, or null without a parallel. */
    public function parallelGap(): ?float
    {
        if ($this->parallel === null || (float) $this->official <= 0) {
            return null;
        }

        return ((float) $this->parallel / (float) $this->official - 1) * 100;
    }

    public function toBolivares(float $dollars): float
    {
        return round($dollars * (float) $this->official, 2);
    }

    /** "889,5754": the rate reads with its four BCV decimals on every screen. */
    public static function formatRate(float|string|null $rate): string
    {
        return $rate === null ? '—' : number_format((float) $rate, 4, ',', '.');
    }

    /** "Bs 70.276,46": bolivars read the same on every screen. */
    public static function formatBolivares(float $amount): string
    {
        return 'Bs '.number_format($amount, 2, ',', '.');
    }
}
