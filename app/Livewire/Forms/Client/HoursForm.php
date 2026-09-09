<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Client;

use App\Classes\Main\Client;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use App\Models\BusinessHour;
use Illuminate\Support\Facades\Auth;

/**
 * Opening-hours card of "Mi negocio": one row per day, several shifts per
 * row — the split day LatAm businesses actually keep. Monday can be stamped
 * onto the weekdays in one click, GBP-style. The save replaces the whole
 * week, so the screen state IS the week.
 */
class HoursForm extends BaseForm
{
    /** Monday first, 0 = Sunday like date("w"), matching Schedule::week(). */
    private const DAYS = [1, 2, 3, 4, 5, 6, 0];

    /** @var array<int, list<array{opens_at: string, closes_at: string}>> */
    public array $week = [];

    /** Called from the component's `mount()`, not a Form hook. */
    public function setup(): void
    {
        $this->week = $this->weekFromStore();
    }

    /**
     * Opening a closed day starts one SUGGESTED shift (owner's call: a filled
     * example beats two empty clocks); closing one drops them all.
     */
    public function toggleDay(int $day): void
    {
        if (! array_key_exists($day, $this->week)) {
            return;
        }

        $this->week[$day] = $this->week[$day] === [] ? [$this->suggestedShift()] : [];
    }

    public function addShift(int $day): void
    {
        if (! array_key_exists($day, $this->week)) {
            return;
        }

        $this->week[$day][] = $this->blankShift();
    }

    /** A day left without shifts reads as closed; the switch follows on its own. */
    public function removeShift(int $day, int $index): void
    {
        unset($this->week[$day][$index]);

        $this->week[$day] = array_values($this->week[$day]);
    }

    public function applyWeekdays(): void
    {
        foreach ([2, 3, 4, 5] as $day) {
            $this->week[$day] = $this->week[1];
        }
    }

    public function save(): NotificationDto
    {
        $schedule = $this->client()->schedule();

        if ($schedule === null) {
            return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
        }

        $this->validateServiceData();

        // Built from the known day keys only: an id injected into the public
        // array can never reach the table.
        $week = [];

        foreach (self::DAYS as $day) {
            $week[$day] = array_map(
                fn (array $shift): array => ['opens_at' => $shift['opens_at'], 'closes_at' => $shift['closes_at']],
                $this->week[$day] ?? [],
            );
        }

        if ($week === $this->weekFromStore()) {
            return new NotificationDto(__('notifications.no_changes'), NotificationType::Info);
        }

        return $this->tryAction(function () use ($schedule, $week): NotificationDto {

            $business = $schedule->save($week);

            $this->week = $this->weekFromStore();

            return $this->notificationService()->updatedRelated($business);

        }, __('notifications.not_updated'));
    }

    /** Rebuilt per request: a Livewire form cannot hold it in a constructor. */
    private function client(): Client
    {
        return Client::for(Auth::user());
    }

    /**
     * The stored week as screen shifts. Postgres hands times back with
     * seconds, which the time inputs and the comparison must not see.
     *
     * @return array<int, list<array{opens_at: string, closes_at: string}>>
     */
    private function weekFromStore(): array
    {
        $stored = $this->client()->schedule()?->week() ?? [];

        return collect(self::DAYS)
            ->mapWithKeys(fn (int $day): array => [$day => array_map(
                fn (BusinessHour $shift): array => [
                    'opens_at' => substr($shift->opens_at, 0, 5),
                    'closes_at' => substr($shift->closes_at, 0, 5),
                ],
                $stored[$day] ?? [],
            )])
            ->all();
    }

    /**
     * @return array{opens_at: string, closes_at: string}
     */
    private function suggestedShift(): array
    {
        return ['opens_at' => '09:00', 'closes_at' => '18:00'];
    }

    /**
     * A SECOND shift starts empty: the split of a day is the business's own.
     *
     * @return array{opens_at: string, closes_at: string}
     */
    private function blankShift(): array
    {
        return ['opens_at' => '', 'closes_at' => ''];
    }

    protected function transformServiceData(): array
    {
        return ['week' => $this->week];
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return [
            'week' => ['array'],
            'week.*' => ['array'],
            'week.*.*.opens_at' => ['required', 'date_format:H:i'],
            'week.*.*.closes_at' => ['required', 'date_format:H:i', 'after:week.*.*.opens_at'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function getValidationAttributes(): array
    {
        return [
            'week.*.*.opens_at' => config('nicename.opens_at'),
            'week.*.*.closes_at' => config('nicename.closes_at'),
        ];
    }
}
