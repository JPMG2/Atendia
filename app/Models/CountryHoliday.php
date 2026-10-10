<?php

declare(strict_types=1);

namespace App\Models;

use App\Interfaces\Catalog\DataTable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One national holiday of one country: a fixed date, a day counted from
 * Easter Sunday, or the exact date of ONE year. Shared by every business of
 * that country, so it carries no `business_id` — like {@see Country} itself.
 */
#[Fillable(['country_id', 'name', 'month', 'day', 'easter_offset', 'on_date', 'is_active'])]
class CountryHoliday extends Model implements DataTable
{
    use LogsActivity;

    public const string KIND_FIXED = 'fixed';

    public const string KIND_EASTER = 'easter';

    public const string KIND_ONCE = 'once';

    /** Who added, moved or switched off a day, and from what: the audit screen reads it. */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['country_id', 'name', 'month', 'day', 'easter_offset', 'on_date', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('catalog');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'month' => 'integer',
            'day' => 'integer',
            'easter_offset' => 'integer',
            'on_date' => 'date:Y-m-d',
            'is_active' => 'boolean',
        ];
    }

    /** Which of the three shapes this row is: the date it holds decides, never a stored label. */
    public function kind(): string
    {
        return match (true) {
            $this->on_date !== null => self::KIND_ONCE,
            $this->easter_offset !== null => self::KIND_EASTER,
            default => self::KIND_FIXED,
        };
    }

    /**
     * The holidays as the catalog list shows them, by country and then by calendar.
     *
     * @return Collection<int, array{id: int, country: string, name: string, kind: string, when: string, active: bool}>
     */
    public function catalogRows(): Collection
    {
        return $this->newQuery()
            ->with('country:id,name')
            ->get()
            ->sortBy(fn (self $holiday): string => $holiday->country?->name.'|'.$holiday->calendarOrder())
            ->map(fn (self $holiday): array => [
                'id' => $holiday->id,
                'country' => (string) $holiday->country?->name,
                'name' => $holiday->name,
                'kind' => $holiday->kind(),
                'when' => $holiday->describeWhen(),
                'active' => $holiday->is_active,
            ])
            ->values();
    }

    /** Where this row sits among its country's: by calendar day, Easter-relative ones after the fixed. */
    private function calendarOrder(): string
    {
        return ($this->on_date?->format('Y-m-d') ?? sprintf('0000-%02d-%02d', $this->month ?? 0, $this->day ?? 0)).'|'.($this->easter_offset ?? 0);
    }

    /** What makes two recurring rows "the same day" across countries; null for a one-year row, which never repeats. */
    private function recurrenceKey(): ?string
    {
        return match ($this->kind()) {
            self::KIND_FIXED => 'fixed:'.$this->month.'-'.$this->day,
            self::KIND_EASTER => 'easter:'.$this->easter_offset,
            default => null,
        };
    }

    /**
     * The holidays of `$sourceId` worth copying to `$targetId`: the ones that
     * repeat every year (a one-year decree of one country says nothing about
     * another) and that the target does not already have on that day.
     *
     * @return Collection<int, self>
     */
    public static function copyableFrom(int $sourceId, int $targetId): Collection
    {
        $taken = self::query()->where('country_id', $targetId)->get()
            ->map(fn (self $holiday): ?string => $holiday->recurrenceKey())
            ->filter()
            ->all();

        return self::query()
            ->where('country_id', $sourceId)
            ->where('is_active', true)
            ->get()
            ->reject(fn (self $holiday): bool => $holiday->recurrenceKey() === null || in_array($holiday->recurrenceKey(), $taken, true))
            ->sortBy(fn (self $holiday): string => $holiday->calendarOrder())
            ->values();
    }

    /** The country that has holidays loaded, to open a calendar on something; null when none has. */
    public static function firstCountryId(): ?int
    {
        $id = self::query()->where('is_active', true)->orderBy('country_id')->value('country_id');

        return $id === null ? null : (int) $id;
    }

    /** "1 de mayo", "Viernes Santo: 2 días antes de Pascua", "17/08/2026" — the date in words. */
    public function describeWhen(): string
    {
        return match ($this->kind()) {
            self::KIND_ONCE => (string) $this->on_date?->format('d/m/Y'),
            self::KIND_EASTER => match (true) {
                $this->easter_offset === 0 => __('catalog.country_holiday.easter.sunday'),
                $this->easter_offset < 0 => __('catalog.country_holiday.easter.before', ['days' => abs($this->easter_offset)]),
                default => __('catalog.country_holiday.easter.after', ['days' => $this->easter_offset]),
            },
            default => $this->day.' '.__('catalog.country_holiday.of').' '.__('catalog.country_holiday.months.'.$this->month),
        };
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * The country's holidays landed on a year, soonest first.
     *
     * @return Collection<int, array{date: CarbonImmutable, name: string, kind: string}>
     */
    public static function forYear(int $countryId, int $year): Collection
    {
        $easter = self::easter($year);

        return self::query()
            ->where('country_id', $countryId)
            ->where('is_active', true)
            ->get()
            // A one-year holiday belongs to its own year only: next January it is simply not there.
            ->reject(fn (self $holiday): bool => $holiday->kind() === self::KIND_ONCE && $holiday->on_date->year !== $year)
            ->map(fn (self $holiday): array => [
                'date' => match ($holiday->kind()) {
                    self::KIND_ONCE => $holiday->on_date->toImmutable(),
                    self::KIND_EASTER => $easter->addDays($holiday->easter_offset),
                    default => CarbonImmutable::create($year, $holiday->month, $holiday->day),
                },
                'name' => $holiday->name,
                'kind' => $holiday->kind(),
            ])
            ->sortBy(fn (array $holiday): string => $holiday['date']->format('Y-m-d'))
            ->values();
    }

    /** The one-year holiday a country has on an exact date, switched on or off; null when there is none. */
    public static function onceOn(int $countryId, string $date): ?self
    {
        return self::query()->where('country_id', $countryId)->whereDate('on_date', $date)->first();
    }

    /**
     * Easter Sunday by the anonymous Gregorian algorithm. Computed and not
     * stored: it is the same arithmetic every year, and a stored table would
     * be one more thing that runs out.
     */
    public static function easter(int $year): CarbonImmutable
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);

        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return CarbonImmutable::create($year, $month, $day);
    }
}
