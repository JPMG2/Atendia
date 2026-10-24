<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Actions\Agenda\CancelAppointmentsForClosure;
use App\Models\Business;
use App\Models\BusinessClosure;
use App\Models\CountryHoliday;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

/**
 * The days the business does NOT open: a holiday, a family matter, a fortnight
 * at the beach. The web's hours card and the mobile app both read and write
 * through here, so the rules (and what counts as "already loaded") live once.
 *
 * A range travels as ISO ("Y-m-d" or "Y-m-d..Y-m-d"): the one shape every
 * caller downstream reads. A single day is stored as a range of one.
 */
class Closures
{
    public function __construct(private Business $business) {}

    /**
     * What is still ahead: a closure that already passed is noise on a screen
     * she opens to plan.
     *
     * @var Collection<int, BusinessClosure>
     */
    public Collection $upcoming {
        get => $this->business->closures()->upcoming()->get();
    }

    /**
     * From today to a year ahead, which is as far as anyone plans and as far
     * as the calendar can be trusted without a decree.
     *
     * @var SupportCollection<int, array{date: CarbonImmutable, name: string}>
     */
    public SupportCollection $holidaysAhead {
        get {
            $countryId = $this->business->country_id;

            if ($countryId === null) {
                return collect();
            }

            $today = CarbonImmutable::today();
            $limit = $today->addYear();

            return CountryHoliday::forYear($countryId, (int) $today->format('Y'))
                ->concat(CountryHoliday::forYear($countryId, (int) $today->format('Y') + 1))
                ->filter(fn (array $holiday): bool => $holiday['date']->betweenIncluded($today, $limit))
                ->values();
        }
    }

    /**
     * The one set of rules the web's form and the app's endpoint both validate with.
     * "Both or neither" on the special hours: a day that opens at 09:00 and never
     * closes is not special hours, it is half an answer.
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'range' => ['required', 'string', 'regex:/^\\d{4}-\\d{2}-\\d{2}(\\.\\.\\d{4}-\\d{2}-\\d{2})?$/', self::realRange(...)],
            'reason' => ['nullable', 'string', 'max:80'],
            'opens_at' => ['nullable', 'date_format:H:i', 'required_with:closes_at'],
            'closes_at' => ['nullable', 'date_format:H:i', 'required_with:opens_at', 'different:opens_at'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return [
            'range' => __('client.business.hours.closure_dates'),
            'reason' => __('client.business.hours.closure_reason'),
            'opens_at' => __('client.business.hours.opens'),
            'closes_at' => __('client.business.hours.closes'),
        ];
    }

    /**
     * The picker only offers real days, but the app types them: "2026-13-45"
     * matches the shape and is no day, and a range must not end before it starts.
     *
     * @param  \Closure(string): mixed  $fail
     */
    private static function realRange(string $attribute, mixed $value, \Closure $fail): void
    {
        // The `string` rule already refuses anything else, but the rules after it still run.
        if (! is_string($value)) {
            return;
        }

        [$from, $to] = self::split($value);

        // The plain PHP parser answers false on garbage; Carbon's strict mode would throw.
        $start = \DateTimeImmutable::createFromFormat('!Y-m-d', $from);
        $end = \DateTimeImmutable::createFromFormat('!Y-m-d', $to);

        // Years 0000 and 9999 parse in PHP and are refused by the database: a closure is planned within living memory.
        $real = $start !== false && $end !== false
            && $start->format('Y-m-d') === $from && $end->format('Y-m-d') === $to
            && $end >= $start
            && (int) $start->format('Y') >= 2000 && (int) $end->format('Y') <= 2100;

        if (! $real) {
            $fail(__('client.business.hours.closure_range_invalid'));
        }
    }

    /**
     * One date or two; a single day is a range of one.
     *
     * @return array{string, string}
     */
    public static function split(string $range): array
    {
        [$from, $to] = array_pad(explode('..', trim($range)), 2, null);

        return [$from, $to ?? $from];
    }

    /**
     * Loads a closure unless one already covers any of those days.
     *
     * @return bool False when the days were already loaded: nothing is written.
     */
    public function add(string $range, ?string $reason, ?string $opensAt, ?string $closesAt): bool
    {
        [$startsOn, $endsOn] = self::split($range);

        if ($this->covers($startsOn, $endsOn)) {
            return false;
        }

        $reason = $reason === null || trim($reason) === '' ? null : trim($reason);
        $special = $opensAt === null || $closesAt === null || trim($opensAt) === '' || trim($closesAt) === '' ? [null, null] : [trim($opensAt), trim($closesAt)];

        $this->business->closures()->create([
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'reason' => $reason,
            'opens_at' => $special[0],
            'closes_at' => $special[1],
        ]);

        return true;
    }

    /** The hours still standing inside a closed stretch, waiting on someone. */
    public function pendingFor(BusinessClosure $closure): int
    {
        return app(CancelAppointmentsForClosure::class)->pending($closure)->count();
    }

    /**
     * Cancels those hours AND tells the people holding them. Her call, not an
     * automatic one: a message to a customer is never sent behind her back.
     *
     * @return int|null How many people were told; null when the closure is not this business's.
     */
    public function cancelAndTell(int $id): ?int
    {
        // Reached THROUGH the business: an id from the front can never point at another tenant's row.
        $closure = $this->business->closures()->find($id);

        return $closure === null ? null : app(CancelAppointmentsForClosure::class)->handle($closure);
    }

    /** @return bool False when the closure is not this business's. */
    public function remove(int $id): bool
    {
        $closure = $this->business->closures()->find($id);

        $closure?->delete();

        return $closure !== null;
    }

    /** Whether a closure already stands on any day of that stretch. */
    public function covers(string $startsOn, string $endsOn): bool
    {
        return $this->business->closures()
            ->whereDate('starts_on', '<=', $endsOn)
            ->whereDate('ends_on', '>=', $startsOn)
            ->exists();
    }
}
