<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Client;

use App\Actions\Agenda\CancelAppointmentsForClosure;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use App\Models\BusinessClosure;
use App\Models\CountryHoliday;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Auth;

/**
 * The days the business does NOT open, loaded by hand on the hours card: a
 * holiday, a family matter, a fortnight at the beach.
 *
 * The range arrives from the datepicker as ISO ("Y-m-d" or "Y-m-d..Y-m-d"),
 * which is the one shape every caller downstream reads.
 */
class ClosuresForm extends BaseForm
{
    public string $range = '';

    public string $reason = '';

    /** Empty means the day is shut; filled, it opens on these instead. */
    public string $opens_at = '';

    public string $closes_at = '';

    /**
     * What is still ahead: a closure that already passed is noise on a screen
     * she opens to plan.
     *
     * @return Collection<int, BusinessClosure>
     */
    public function upcoming(): Collection
    {
        $business = Auth::user()?->business;

        return $business === null
            ? new Collection
            : $business->closures()->upcoming()->get();
    }

    public function add(): NotificationDto
    {
        $business = Auth::user()?->business;

        if ($business === null) {
            return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
        }

        $this->validateServiceData();

        [$startsOn, $endsOn] = $this->dates();

        if ($this->overlaps($startsOn, $endsOn)) {
            return new NotificationDto(__('client.business.hours.closure_exists'), NotificationType::Info);
        }

        return $this->tryAction(function () use ($business, $startsOn, $endsOn): NotificationDto {

            $business->closures()->create([
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                'reason' => trim($this->reason) === '' ? null : trim($this->reason),
                'opens_at' => $this->specialHours()[0],
                'closes_at' => $this->specialHours()[1],
            ]);

            $this->range = '';
            $this->reason = '';
            $this->opens_at = '';
            $this->closes_at = '';

            return new NotificationDto(__('client.business.hours.closure_added'), NotificationType::Success);

        }, __('notifications.not_updated'));
    }

    /**
     * The country's national holidays still ahead, in one click. Whatever she
     * already loaded by hand wins: nothing here overwrites her own decision.
     */
    public function importCountryHolidays(): NotificationDto
    {
        $business = Auth::user()?->business;
        $country = $business?->country;

        if ($business === null || $country === null) {
            return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
        }

        return $this->tryAction(function () use ($business, $country): NotificationDto {

            $added = 0;

            foreach ($this->holidaysAhead($country->id) as $holiday) {
                $date = $holiday['date']->format('Y-m-d');

                if ($this->overlaps($date, $date)) {
                    continue;
                }

                $business->closures()->create([
                    'starts_on' => $date,
                    'ends_on' => $date,
                    'reason' => $holiday['name'],
                ]);

                $added++;
            }

            return $added === 0
                ? new NotificationDto(__('client.business.hours.holidays_none'), NotificationType::Info)
                : new NotificationDto(
                    trans_choice('client.business.hours.holidays_added', $added, ['count' => $added]),
                    NotificationType::Success,
                );

        }, __('notifications.not_updated'));
    }

    /**
     * From today to a year ahead, which is as far as anyone plans and as far
     * as the calendar can be trusted without a decree.
     *
     * @return SupportCollection<int, array{date: CarbonImmutable, name: string}>
     */
    public function holidaysAhead(int $countryId): SupportCollection
    {
        $today = CarbonImmutable::today();
        $limit = $today->addYear();

        return CountryHoliday::forYear($countryId, (int) $today->format('Y'))
            ->concat(CountryHoliday::forYear($countryId, (int) $today->format('Y') + 1))
            ->filter(fn (array $holiday): bool => $holiday['date']->betweenIncluded($today, $limit))
            ->values();
    }

    /** The hours still standing inside a closed stretch, waiting on someone. */
    public function pendingFor(BusinessClosure $closure): int
    {
        return app(CancelAppointmentsForClosure::class)->pending($closure)->count();
    }

    /**
     * Cancels those hours AND tells the people holding them. Her call, not an
     * automatic one: a message to a customer is never sent behind her back.
     */
    public function notify(int $id): NotificationDto
    {
        $closure = Auth::user()?->business?->closures()->find($id);

        if ($closure === null) {
            return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
        }

        return $this->tryAction(function () use ($closure): NotificationDto {

            $told = app(CancelAppointmentsForClosure::class)->handle($closure);

            return new NotificationDto(
                trans_choice('client.business.hours.closure_notified', $told, ['count' => $told]),
                $told === 0 ? NotificationType::Info : NotificationType::Success,
            );

        }, __('notifications.not_updated'));
    }

    public function remove(int $id): NotificationDto
    {
        // Reached THROUGH the business: an id from the front can never point
        // at another tenant's row.
        $closure = Auth::user()?->business?->closures()->find($id);

        if ($closure === null) {
            return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
        }

        return $this->tryAction(function () use ($closure): NotificationDto {

            $closure->delete();

            return new NotificationDto(__('client.business.hours.closure_removed'), NotificationType::Success);

        }, __('notifications.not_updated'));
    }

    /**
     * The picker sends one date or two; a single day is stored as a range of
     * one so everything downstream asks the same question.
     *
     * @return array{string, string}
     */
    private function dates(): array
    {
        [$from, $to] = array_pad(explode('..', trim($this->range)), 2, null);

        return [$from, $to ?? $from];
    }

    private function overlaps(string $startsOn, string $endsOn): bool
    {
        return (bool) Auth::user()?->business?->closures()
            ->whereDate('starts_on', '<=', $endsOn)
            ->whereDate('ends_on', '>=', $startsOn)
            ->exists();
    }

    /**
     * Both or neither: one lone time is not a day's hours.
     *
     * @return array{?string, ?string}
     */
    private function specialHours(): array
    {
        $opens = trim($this->opens_at);
        $closes = trim($this->closes_at);

        return $opens === '' || $closes === '' ? [null, null] : [$opens, $closes];
    }

    protected function transformServiceData(): array
    {
        return [
            'range' => trim($this->range),
            'reason' => trim($this->reason),
            'opens_at' => trim($this->opens_at) === '' ? null : trim($this->opens_at),
            'closes_at' => trim($this->closes_at) === '' ? null : trim($this->closes_at),
        ];
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        // Validated as the ONE field the screen shows: the picker writes the
        // ISO day, or the two days of a range, into that hidden input.
        return [
            'range' => ['required', 'string', 'regex:/^\\d{4}-\\d{2}-\\d{2}(\\.\\.\\d{4}-\\d{2}-\\d{2})?$/'],
            'reason' => ['nullable', 'string', 'max:80'],
            // Required WITH each other: a day that opens at 09:00 and never
            // closes is not special hours, it is half an answer.
            'opens_at' => ['nullable', 'date_format:H:i', 'required_with:closes_at'],
            'closes_at' => ['nullable', 'date_format:H:i', 'required_with:opens_at', 'different:opens_at'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function getValidationAttributes(): array
    {
        return [
            'range' => __('client.business.hours.closure_dates'),
            'reason' => __('client.business.hours.closure_reason'),
            'opens_at' => __('client.business.hours.opens'),
            'closes_at' => __('client.business.hours.closes'),
        ];
    }
}
