<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Livewire\Forms\Agenda\AppointmentForm;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A booking of "Agenda", rule for rule as the web's sheet validates it. Booking takes the
 * person, the service, the day, the hour and the note; moving takes only the day and the hour
 * (the person and the service are the reasons the slot exists: they are not read).
 */
class SaveBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $text = fn (string $key): ?string => is_scalar($this->input($key)) && ! is_bool($this->input($key)) && $this->input($key) !== '' ? (string) $this->input($key) : null;

        $this->replace([
            'customer_id' => $this->moving() ? null : ($text('customer_id')),
            'service_id' => $this->moving() ? null : ($text('service_id')),
            'day' => $text('day'),
            'time' => $text('time'),
            'notes' => $this->moving() ? null : ($text('notes') === null ? null : trim((string) $text('notes'))),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return AppointmentForm::fieldRules($this->user()->business_id, $this->moving());
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return AppointmentForm::fieldNames();
    }

    private function moving(): bool
    {
        return $this->route('booking') !== null;
    }
}
