<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Agenda;

use App\Classes\Main\Agenda;
use App\Classes\Main\Client;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use App\Rules\AttributeValidator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * The booking sheet: a new slot for a customer, or an existing one moved to
 * another hour. Editing only ever changes the hour — who comes and what for
 * are the reasons the slot exists.
 */
class AppointmentForm extends BaseForm
{
    public ?int $editingId = null;

    public ?int $customer_id = null;

    public ?int $service_id = null;

    public string $day = '';

    public string $time = '';

    public ?string $notes = null;

    /** Called from the component, not a Form hook: `$day` is the day on screen. */
    public function setup(string $day, ?int $appointmentId = null, ?string $time = null): void
    {
        $this->resetErrorBag();
        $this->editingId = $appointmentId;
        $this->day = $day;
        $this->time = $time ?? '';

        $appointment = $appointmentId === null ? null : $this->agenda()?->find($appointmentId);

        $this->customer_id = $appointment?->customer_id;
        $this->service_id = $appointment?->service_id;
        $this->notes = $appointment?->notes;

        if ($appointment !== null) {
            $local = $appointment->starts_at->setTimezone($appointment->business->localTimezone());
            $this->day = $local->format('Y-m-d');
            $this->time = $local->format('H:i');
        }
    }

    public function save(): NotificationDto
    {
        $agenda = $this->agenda();

        if ($agenda === null) {
            return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
        }

        $validated = $this->validateServiceData();

        return $this->tryAction(function () use ($agenda, $validated): NotificationDto {

            $startsAt = $validated['day'].' '.$validated['time'];

            if ($this->editingId !== null) {
                $agenda->reschedule($this->editingId, $startsAt);

                return new NotificationDto(__('agenda.notify.moved'), NotificationType::Success);
            }

            $agenda->book([
                'customer_id' => (int) $validated['customer_id'],
                'service_id' => $validated['service_id'],
                'starts_at' => $startsAt,
                'notes' => $validated['notes'],
            ]);

            return new NotificationDto(__('agenda.notify.booked'), NotificationType::Success);

        }, __('agenda.notify.slot_taken'));
    }

    private function agenda(): ?Agenda
    {
        return Client::for(Auth::user())->agenda;
    }

    protected function transformServiceData(): array
    {
        return [
            'customer_id' => $this->customer_id,
            'service_id' => $this->service_id,
            'day' => $this->day,
            'time' => $this->time,
            'notes' => $this->notes,
        ];
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        $businessId = Auth::user()?->business_id;

        return [
            // Moving a booking never re-picks the person: only the hour travels.
            'customer_id' => [
                $this->editingId === null ? 'required' : 'nullable',
                'integer',
                Rule::exists('customers', 'id')->where('business_id', $businessId),
            ],
            'service_id' => [
                'nullable',
                'integer',
                Rule::exists('services', 'id')->where('business_id', $businessId)->where('is_bookable', true),
            ],
            'day' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:255', AttributeValidator::xssFree()],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function getValidationAttributes(): array
    {
        return [
            'customer_id' => __('agenda.form.customer'),
            'service_id' => __('agenda.form.service'),
            'day' => __('agenda.form.day'),
            'time' => __('agenda.form.time'),
            'notes' => __('agenda.form.notes'),
        ];
    }
}
