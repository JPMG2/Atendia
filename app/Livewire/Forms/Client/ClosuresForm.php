<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Client;

use App\Actions\Business\ImportCountryHolidays;
use App\Classes\Main\Client;
use App\Classes\Main\Closures;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use App\Models\BusinessClosure;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Auth;

/**
 * The days the business does NOT open, loaded by hand on the hours card: a
 * holiday, a family matter, a fortnight at the beach. The rules and the
 * writes live in {@see Closures}, shared with the mobile app's endpoints.
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
     * @return Collection<int, BusinessClosure>
     */
    public function upcoming(): Collection
    {
        return $this->closures()?->upcoming ?? new Collection;
    }

    public function add(): NotificationDto
    {
        $closures = $this->closures();

        if ($closures === null) {
            return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
        }

        $this->validateServiceData();

        return $this->tryAction(function () use ($closures): NotificationDto {

            if (! $closures->add($this->range, $this->reason, $this->opens_at, $this->closes_at)) {
                return new NotificationDto(__('client.business.hours.closure_exists'), NotificationType::Info);
            }

            $this->range = '';
            $this->reason = '';
            $this->opens_at = '';
            $this->closes_at = '';

            return new NotificationDto(__('client.business.hours.closure_added'), NotificationType::Success);

        }, __('notifications.not_updated'));
    }

    public function importCountryHolidays(): NotificationDto
    {
        $business = Auth::user()?->business;

        if ($business === null) {
            return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
        }

        return $this->tryAction(function () use ($business): NotificationDto {

            $added = app(ImportCountryHolidays::class)->handle($business);

            if ($added === null) {
                return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
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
     * @return SupportCollection<int, array{date: CarbonImmutable, name: string}>
     */
    public function holidaysAhead(int $countryId): SupportCollection
    {
        // The country is the business's own; the id only tells the screen it has one.
        return $countryId > 0 ? ($this->closures()?->holidaysAhead ?? collect()) : collect();
    }

    /** The hours still standing inside a closed stretch, waiting on someone. */
    public function pendingFor(BusinessClosure $closure): int
    {
        return $this->closures()?->pendingFor($closure) ?? 0;
    }

    public function notify(int $id): NotificationDto
    {
        $closures = $this->closures();

        if ($closures === null) {
            return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
        }

        return $this->tryAction(function () use ($closures, $id): NotificationDto {

            $told = $closures->cancelAndTell($id);

            if ($told === null) {
                return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
            }

            return new NotificationDto(
                trans_choice('client.business.hours.closure_notified', $told, ['count' => $told]),
                $told === 0 ? NotificationType::Info : NotificationType::Success,
            );

        }, __('notifications.not_updated'));
    }

    public function remove(int $id): NotificationDto
    {
        $closures = $this->closures();

        if ($closures === null) {
            return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
        }

        return $this->tryAction(function () use ($closures, $id): NotificationDto {

            return $closures->remove($id)
                ? new NotificationDto(__('client.business.hours.closure_removed'), NotificationType::Success)
                : new NotificationDto(__('notifications.not_found'), NotificationType::Error);

        }, __('notifications.not_updated'));
    }

    /** Rebuilt per request: a Livewire form cannot hold it in a constructor. */
    private function closures(): ?Closures
    {
        return Client::for(Auth::user())->closures;
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
        return Closures::rules();
    }

    /**
     * @return array<string, string>
     */
    protected function getValidationAttributes(): array
    {
        return Closures::attributes();
    }
}
