<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Agenda;

use App\Actions\Agenda\BookAppointment;
use App\Dto\NotificationDto;
use App\Enums\AppointmentSource;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\Customer;
use App\Rules\AttributeValidator;
use App\Services\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * The public booking link: a customer picks an hour and leaves a name and a
 * WhatsApp. No session and no tenant in the request, so every write runs
 * inside the business's own context.
 */
class PublicBookingForm extends BaseForm
{
    public ?int $service_id = null;

    public string $day = '';

    public string $time = '';

    public string $name = '';

    public string $phone = '';

    public function setup(string $day): void
    {
        $this->resetErrorBag();
        $this->day = $day;
        $this->time = '';
    }

    /** @return NotificationDto|Appointment the booking, or why it could not be made */
    public function book(Business $business): NotificationDto|Appointment
    {
        $validated = $this->validateServiceData();

        return app(Tenant::class)->for($business->id, function () use ($business, $validated): NotificationDto|Appointment {
            $service = $validated['service_id'] === null
                ? null
                : $business->services()->where('is_bookable', true)->find($validated['service_id']);

            $customer = $this->customerFor($business, $validated['phone'], $validated['name']);

            try {
                return app(BookAppointment::class)->handle(
                    $business,
                    $customer,
                    CarbonImmutable::parse($validated['day'].' '.$validated['time'], $business->localTimezone()),
                    $service,
                    AppointmentSource::Link,
                );
            } catch (RuntimeException) {
                // Somebody took the hour between the page load and the tap.
                return new NotificationDto(__('agenda.public.taken'), NotificationType::Warning);
            }
        });
    }

    /**
     * The person behind the phone: an existing customer of this business, or
     * a new record. A name written here never overwrites a curated one.
     */
    private function customerFor(Business $business, string $phone, string $name): Customer
    {
        $customer = $business->customers()->firstOrCreate(
            ['phone' => $phone],
            ['business_id' => $business->id, 'name' => $name, 'first_seen_at' => now()],
        );

        if ($customer->name === null) {
            $customer->update(['name' => $name]);
        }

        return $customer;
    }

    protected function transformServiceData(): array
    {
        return [
            'service_id' => $this->service_id,
            'day' => $this->day,
            'time' => $this->time,
            'name' => $this->name,
            'phone' => preg_replace('/\D/', '', $this->phone),
        ];
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return [
            'service_id' => ['nullable', 'integer', Rule::exists('services', 'id')->where('is_bookable', true)],
            'day' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'name' => [...AttributeValidator::stringValid(true, '2'), 'max:60'],
            'phone' => ['required', ...AttributeValidator::digitValid('8', false), 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function getValidationAttributes(): array
    {
        return [
            'service_id' => __('agenda.form.service'),
            'day' => __('agenda.form.day'),
            'time' => __('agenda.form.time'),
            'name' => __('agenda.public.name'),
            'phone' => __('agenda.public.phone'),
        ];
    }
}
