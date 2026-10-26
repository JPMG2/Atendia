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
use Illuminate\Support\Str;

/**
 * The agenda piece: the booked slots and the free hours left. Every write is
 * pinned to THIS business, whatever ids a request carries.
 */
class Agenda
{
    public function __construct(private Business $business) {}

    /**
     * Off by default: a bakery has no slots to give. The cast is the point —
     * the column defaults to false, but a business built without it (a factory,
     * a partial select) hands back null and a `bool` hook dies with the screen.
     */
    public bool $isOn {
        get => (bool) $this->business->appointments_enabled;
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
     * The bookings the screen paints: that day, or the whole week holding it.
     * The screen's export asks the same question, so the file is the view.
     *
     * @return Collection<int, Appointment>
     */
    public function inView(CarbonImmutable $day, string $view): Collection
    {
        if ($view !== 'week') {
            return $this->day($day);
        }

        $monday = $day->setTimezone($this->business->localTimezone())->startOfWeek();

        return Appointment::between($this->business, $monday, $monday->addDays(7));
    }

    /**
     * Every booking the business has ever had, newest first, cancelled ones
     * included: what "export everything" means for a calendar.
     *
     * @var Collection<int, Appointment>
     */
    public Collection $everything {
        get => $this->business->appointments()
            ->with('customer:id,name,phone,profile_name', 'service:id,name,duration_minutes')
            ->orderByDesc('starts_at')
            ->get();
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

    /**
     * The hours offered for a day, keyed by themselves so a picker shows and stores "HH:MM". A booking
     * being moved keeps its own hour on the list: it is where it already is.
     *
     * @return array<string, string>
     */
    public function hoursFor(string $day, ?string $keep = null, ?int $serviceId = null): array
    {
        // The service's own duration decides which hours fit: offering one the save then refuses is a lie.
        $hours = array_map(
            fn (CarbonImmutable $slot): string => $slot->format('H:i'),
            $this->freeSlots(CarbonImmutable::parse($day, $this->business->localTimezone())->startOfDay(), $serviceId),
        );

        if ($keep !== null && ! in_array($keep, $hours, true)) {
            $hours[] = $keep;
            sort($hours);
        }

        return array_combine($hours, $hours);
    }

    /**
     * The words the app prints for "Agenda", the web screen's own (in "citas" for Venezuela).
     *
     * @var array<string, mixed>
     */
    public array $copy {
        get => [
            'title' => __('agenda.title'),
            'sub' => __('agenda.sub'),
            'book' => __('agenda.book'),
            'today' => __('agenda.today'),
            'previous' => __('agenda.previous'),
            'next' => __('agenda.next'),
            'pickDay' => __('agenda.pick_day'),
            'viewDay' => __('agenda.view_day'),
            'viewWeek' => __('agenda.view_week'),
            'empty' => ['title' => __('agenda.empty.title'), 'body' => __('agenda.empty.body')],
            'free' => ['title' => __('agenda.free.title'), 'sub' => __('agenda.free.sub'), 'none' => __('agenda.free.none')],
            'off' => ['title' => __('agenda.off.title'), 'body' => __('agenda.off.body'), 'cta' => __('agenda.off.cta')],
            'cancelAccept' => __('agenda.cancel_accept'),
            'form' => [
                'customer' => __('agenda.form.customer'),
                'customerPlaceholder' => __('agenda.form.customer_placeholder'),
                'customerEmpty' => __('agenda.form.customer_empty'),
                'service' => __('agenda.form.service'),
                'servicePlaceholder' => __('agenda.form.service_placeholder'),
                'day' => __('agenda.form.day'),
                'time' => __('agenda.form.time'),
                'timePlaceholder' => __('agenda.form.time_placeholder'),
                'notes' => __('agenda.form.notes'),
                'notesHint' => __('agenda.form.notes_hint'),
                'cancel' => __('agenda.form.cancel'),
            ],
        ];
    }

    /**
     * One booking as the web's row says it: the hour, who, what for and how long, the state when it is
     * not the plain "confirmed", and the actions only while it still stands.
     *
     * @return array<string, mixed>
     */
    public function rowOf(Appointment $booking): array
    {
        $local = $booking->starts_at->setTimezone($this->business->localTimezone());
        $open = $booking->status === AppointmentStatus::Confirmed;

        return [
            'id' => $booking->id,
            'time' => $local->format('H:i'),
            'name' => $booking->customer?->displayName() ?? __('agenda.no_name'),
            'state' => $open ? null : __('agenda.status.'.$booking->status->value),
            'service' => $booking->service?->name ?? __('agenda.plain_slot'),
            'minutes' => $booking->durationMinutes().' min',
            'phone' => $booking->customer?->phone === null ? null : '+'.$booking->customer->phone,
            'notes' => $booking->notes,
            'open' => $open,
            'markDoneLabel' => __('agenda.mark_done'),
            'moveLabel' => __('agenda.move'),
            'cancelLabel' => __('agenda.cancel'),
            'cancelTitle' => __('agenda.cancel_title'),
            'cancelMessage' => __('agenda.cancel_message', ['when' => $local->format('H:i')]),
        ];
    }

    /**
     * The agenda screen in one answer: the day or the week on screen, the free hours of the day, and
     * what an agenda that is switched off says instead.
     *
     * @return array<string, mixed>
     */
    public function page(CarbonImmutable $day, string $view): array
    {
        $day = $day->setTimezone($this->business->localTimezone())->startOfDay();
        $locale = app()->getLocale();

        if (! $this->isOn) {
            return ['isOn' => false, 'day' => $day->format('Y-m-d'), 'view' => $view];
        }

        $week = $view === 'week' ? $this->week($day) : null;
        $bookings = $view === 'week' ? null : $this->day($day);

        return [
            'isOn' => true,
            'day' => $day->format('Y-m-d'),
            'view' => $view,
            'today' => $this->today->format('Y-m-d'),
            'heading' => $view === 'week'
                ? __('agenda.week_of', ['day' => $day->startOfWeek()->translatedFormat('j/n')])
                : Str::ucfirst($day->locale($locale)->translatedFormat('l j \d\e F')),
            'count' => $week === null ? $bookings->count() : collect($week)->sum(fn (array $slot): int => $slot['bookings']->count()),
            'bookings' => $bookings?->map(fn (Appointment $booking): array => $this->rowOf($booking))->values()->all() ?? [],
            'week' => $week === null ? [] : array_map(fn (array $slot): array => [
                'date' => $slot['date']->format('Y-m-d'),
                'label' => Str::ucfirst($slot['date']->locale($locale)->translatedFormat('D')),
                'short' => $slot['date']->format('j/n'),
                'current' => $slot['date']->format('Y-m-d') === $day->format('Y-m-d'),
                'bookings' => $slot['bookings']->map(fn (Appointment $booking): array => [
                    'time' => $booking->starts_at->setTimezone($this->business->localTimezone())->format('H:i'),
                    'name' => $booking->customer?->displayName() ?? __('agenda.no_name'),
                ])->values()->all(),
                'empty' => $slot['closed'] ? __('agenda.week_closed') : __('agenda.week_none'),
                'free' => trans_choice('agenda.week_free', $slot['free'], ['count' => $slot['free']]),
            ], $week),
            'hours' => $view === 'week' ? [] : array_values($this->hoursFor($day->format('Y-m-d'))),
        ];
    }

    /**
     * The sheet the web opens: a new booking (optionally with an hour a free-hour chip picked) or a
     * booking being moved, which only ever changes the hour.
     *
     * @return array<string, mixed>
     */
    public function sheetOf(string $day, ?Appointment $booking = null, ?string $time = null): array
    {
        $local = $booking?->starts_at->setTimezone($this->business->localTimezone());
        $day = $local?->format('Y-m-d') ?? $day;
        $time = $local?->format('H:i') ?? $time ?? '';

        return [
            'id' => $booking?->id,
            'moving' => $booking !== null,
            'title' => $booking === null ? __('agenda.form.title') : __('agenda.form.move_title'),
            'sub' => $booking === null ? __('agenda.form.sub') : __('agenda.form.move_sub'),
            'save' => $booking === null ? __('agenda.form.save') : __('agenda.form.move_save'),
            'values' => ['customer_id' => $booking?->customer_id, 'service_id' => $booking?->service_id, 'day' => $day, 'time' => $time, 'notes' => $booking?->notes],
            // Moving never re-picks the person: only who it is, for the line above the hour.
            'customer' => $booking?->customer?->displayName() ?? ($booking === null ? null : __('agenda.no_name')),
            // The people the sheet picks from, most recently active first, as the web's combobox lists them.
            'customers' => $booking !== null ? [] : collect($this->customerOptions)->map(fn (string $name, int $id): array => ['id' => $id, 'name' => $name])->values()->all(),
            'services' => $this->bookableServices->map(fn (Service $service): array => ['id' => $service->id, 'name' => $service->name])->all(),
            'hours' => array_values($this->hoursFor($day, $booking === null ? null : $time, $booking?->service_id)),
        ];
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
