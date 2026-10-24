<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Client;

use App\Classes\Main\Client;
use App\Classes\Main\Schedule;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Opening-hours card of "Mi negocio": one row per day, several shifts per
 * row — the split day LatAm businesses actually keep. Monday can be stamped
 * onto the weekdays in one click, GBP-style. The save replaces the whole
 * week, so the screen state IS the week.
 */
class HoursForm extends BaseForm
{
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
        $schedule = $this->client()->schedule;

        if ($schedule === null) {
            return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
        }

        $this->validateServiceData();
        $this->assertShiftsDoNotOverlap();

        $week = $schedule->normalized($this->week);

        if (! $schedule->differsFrom($week)) {
            return new NotificationDto(__('notifications.no_changes'), NotificationType::Info);
        }

        return $this->tryAction(function () use ($schedule, $week): NotificationDto {

            $business = $schedule->save($week);

            $this->week = $this->weekFromStore();

            return $this->notificationService()->updatedRelated($business);

        }, __('notifications.not_updated'));
    }

    /**
     * The rule lives in {@see Schedule}, shared with the mobile app's endpoint.
     *
     * @throws ValidationException
     */
    private function assertShiftsDoNotOverlap(): void
    {
        $errors = Schedule::overlapErrors($this->week);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** Rebuilt per request: a Livewire form cannot hold it in a constructor. */
    private function client(): Client
    {
        return Client::for(Auth::user());
    }

    /**
     * @return array<int, list<array{opens_at: string, closes_at: string}>>
     */
    private function weekFromStore(): array
    {
        return $this->client()->schedule?->times ?? array_fill_keys(Schedule::DAYS, []);
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
        return Schedule::rules();
    }

    /**
     * @return array<string, string>
     */
    protected function getValidationAttributes(): array
    {
        return Schedule::attributes();
    }
}
