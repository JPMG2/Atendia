<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Actions\Agenda\BookAppointment;
use App\Actions\Agenda\CancelAppointment;
use App\Actions\Agenda\RescheduleAppointment;
use App\Actions\Agenda\SaveAgendaSettings;
use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Service;
use App\Services\Agenda\SlotFinder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * The agenda piece: the booked slots and the free hours left. Every write is
 * pinned to THIS business, whatever ids a request carries.
 */
class Agenda
{
    public function __construct(private Business $business) {}

    /** Off by default: a bakery has no slots to give. */
    public bool $isOn {
        get => $this->business->appointments_enabled;
    }

    /**
     * The services that take a booking, with the duration each slot lasts.
     *
     * @var Collection<int, Service>
     */
    public Collection $bookableServices {
        get => $this->business->services()
            ->where('is_active', true)
            ->where('is_bookable', true)
            ->orderBy('name')
            ->get(['id', 'name', 'duration_minutes']);
    }

    /**
     * The people a booking can be made for, newest first: what the sheet's
     * picker shows.
     *
     * @var array<int, string>
     */
    public array $customerOptions {
        get => $this->business->customers()
            ->orderByDesc('last_activity_at')
            ->get(['id', 'name', 'profile_name', 'phone'])
            ->mapWithKeys(fn (Customer $customer): array => [
                $customer->id => $customer->displayName() ?? '+'.$customer->phone,
            ])
            ->all();
    }

    /** Today in the business's own timezone — where the agenda opens. */
    public CarbonImmutable $today {
        get => CarbonImmutable::now($this->business->localTimezone())->startOfDay();
    }

    /**
     * One day of the agenda, earliest first.
     *
     * @return Collection<int, Appointment>
     */
    public function day(CarbonImmutable $day): Collection
    {
        $day = $day->setTimezone($this->business->localTimezone())->startOfDay();

        return Appointment::between($this->business, $day, $day->endOfDay());
    }

    /**
     * The week that holds a day, Monday first: each day with its bookings and
     * how many hours it still has free — what the week view paints.
     *
     * @return list<array{date: CarbonImmutable, bookings: Collection<int, Appointment>, free: int, closed: bool}>
     */
    public function week(CarbonImmutable $day): array
    {
        $monday = $day->setTimezone($this->business->localTimezone())->startOfWeek();
        $booked = Appointment::between($this->business, $monday, $monday->addDays(7))
            ->groupBy(fn (Appointment $appointment): string => $appointment->starts_at->setTimezone($this->business->localTimezone())->format('Y-m-d'));

        return array_map(fn (int $offset): array => [
            'date' => $date = $monday->addDays($offset),
            'bookings' => $booked->get($date->format('Y-m-d')) ?? new Collection,
            'free' => count($this->freeSlots($date)),
            // Closed is about the SCHEDULE, not about hours already gone: a
            // past Monday has no free hours left and was never closed.
            'closed' => $this->business->hours->where('day_of_week', (int) $date->format('w'))->isEmpty(),
        ], range(0, 6));
    }

    /**
     * The free hours of a day for a service (null = a plain slot).
     *
     * @return list<CarbonImmutable>
     */
    public function freeSlots(CarbonImmutable $day, ?int $serviceId = null): array
    {
        return app(SlotFinder::class)->freeSlots($this->business, $day, $this->service($serviceId));
    }

    /** @param  array{customer_id: int, service_id: ?int, starts_at: string, notes: ?string}  $validated */
    public function book(array $validated): Appointment
    {
        return app(BookAppointment::class)->handle(
            $this->business,
            $this->business->customers()->findOrFail($validated['customer_id']),
            CarbonImmutable::parse($validated['starts_at'], $this->business->localTimezone()),
            $this->service($validated['service_id'] ?? null),
            AppointmentSource::Owner,
            $validated['notes'] ?? null,
        );
    }

    public function cancel(int $id): Appointment
    {
        return app(CancelAppointment::class)->handle($this->appointment($id));
    }

    public function reschedule(int $id, string $startsAt): Appointment
    {
        return app(RescheduleAppointment::class)->handle(
            $this->appointment($id),
            CarbonImmutable::parse($startsAt, $this->business->localTimezone()),
        );
    }

    /** What happened with the slot after the fact: it came, or it did not. */
    public function mark(int $id, AppointmentStatus $status): Appointment
    {
        $appointment = $this->appointment($id);
        $appointment->update(['status' => $status]);

        return $appointment;
    }

    /** @param  array{appointments_enabled: bool, appointment_capacity: int, appointments_per_day: ?int, appointment_slot_minutes: int}  $validated */
    public function saveSettings(array $validated): Business
    {
        return app(SaveAgendaSettings::class)->handle($this->business, $validated);
    }

    /** One booking of THIS business, for the sheet that edits it. */
    public function find(int $id): Appointment
    {
        return $this->appointment($id);
    }

    /** Only this business's bookings are ever touched, whatever id arrives. */
    private function appointment(int $id): Appointment
    {
        return $this->business->appointments()->findOrFail($id);
    }

    private function service(?int $serviceId): ?Service
    {
        return $serviceId === null ? null : $this->business->services()->findOrFail($serviceId);
    }
}
