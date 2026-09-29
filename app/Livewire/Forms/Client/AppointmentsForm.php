<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Client;

use App\Classes\Main\Client;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use Illuminate\Support\Facades\Auth;

/**
 * Bookings card of "Mi negocio": whether this business gives slots at all,
 * how many it can hold at once and how long a plain one lasts. Off by
 * default — a bakery has no slots to give.
 */
class AppointmentsForm extends BaseForm
{
    public bool $appointments_enabled = false;

    public int $appointment_capacity = 1;

    public ?int $appointments_per_day = null;

    public int $appointment_slot_minutes = 30;

    /** Called from the component's `mount()`, not a Form hook. */
    public function setup(): void
    {
        $business = Auth::user()?->business;

        $this->appointments_enabled = (bool) $business?->appointments_enabled;
        $this->appointment_capacity = (int) ($business?->appointment_capacity ?? 1);
        $this->appointments_per_day = $business?->appointments_per_day;
        $this->appointment_slot_minutes = (int) ($business?->appointment_slot_minutes ?? 30);
    }

    public function save(): NotificationDto
    {
        $agenda = Client::for(Auth::user())->agenda;

        if ($agenda === null) {
            return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
        }

        $validated = $this->validateServiceData();

        return $this->tryAction(function () use ($agenda, $validated): NotificationDto {

            $agenda->saveSettings($validated);

            return new NotificationDto(__('client.business.appointments.saved'), NotificationType::Success);

        }, __('notifications.not_updated'));
    }

    protected function transformServiceData(): array
    {
        return [
            'appointments_enabled' => $this->appointments_enabled,
            'appointment_capacity' => $this->appointment_capacity,
            'appointments_per_day' => $this->appointments_per_day,
            'appointment_slot_minutes' => $this->appointment_slot_minutes,
        ];
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return [
            'appointments_enabled' => ['boolean'],
            'appointment_capacity' => ['required', 'integer', 'min:1', 'max:50'],
            'appointments_per_day' => ['nullable', 'integer', 'min:1', 'max:500'],
            // Five minutes is the shortest honest slot; a working day is the longest.
            'appointment_slot_minutes' => ['required', 'integer', 'min:5', 'max:480'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function getValidationAttributes(): array
    {
        return [
            'appointment_capacity' => __('client.business.appointments.capacity'),
            'appointments_per_day' => __('client.business.appointments.per_day'),
            'appointment_slot_minutes' => __('client.business.appointments.slot_minutes'),
        ];
    }
}
