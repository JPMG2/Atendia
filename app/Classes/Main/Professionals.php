<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Actions\Professionals\DeleteProfessional;
use App\Actions\Professionals\SaveProfessional;
use App\Dto\ProfessionalDto;
use App\Models\Business;
use App\Models\Professional;
use App\Models\ProfessionalClosure;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

/**
 * The professionals piece: whoever attends the bookings, with their own week,
 * capacity, daily ceiling and the services they attend. Owner-only by the
 * route; every write is pinned to THIS business, whatever ids a request carries.
 */
class Professionals
{
    public function __construct(private Business $business) {}

    /**
     * In the owner's order, with what the list shows of each.
     *
     * @var Collection<int, Professional>
     */
    public Collection $all {
        get => $this->business->professionals()
            ->with(['services:id,name', 'hours', 'closures'])
            ->withCount(['appointments as upcoming' => fn ($booked) => $booked->upcoming()])
            ->get();
    }

    /**
     * The list as the app draws it: the web's own lines for each person, said by
     * the server. Read-only on the phone, so no word of an action travels.
     *
     * @var array{
     *     copy: array{title: string, sub: string, list: string, empty_title: string, empty_body: string, needs_services: string|null},
     *     professionals: list<array{id: int, name: string, inactive: string|null, hours: string, services: string, limits: string|null, upcoming: string|null, away: string|null}>
     * }
     */
    public array $overview {
        get => [
            'copy' => [
                'title' => __('professionals.title'),
                'sub' => __('professionals.sub'),
                'list' => __('professionals.list.title'),
                'empty_title' => __('professionals.list.empty_title'),
                'empty_body' => __('professionals.list.empty_body'),
                'needs_services' => $this->bookableServices->isEmpty() ? __('professionals.needs_services.body') : null,
            ],
            'professionals' => $this->all->map(fn (Professional $professional): array => [
                'id' => $professional->id,
                'name' => $professional->name,
                'inactive' => $professional->is_active ? null : __('professionals.list.inactive'),
                'hours' => $this->hoursLine($professional),
                'services' => trans_choice('professionals.list.services', $professional->services->count(), ['count' => $professional->services->count()]),
                'limits' => $professional->appointments_per_day === null ? null : __('professionals.list.limits', ['per_day' => $professional->appointments_per_day]),
                'upcoming' => $this->upcomingLine($professional),
                'away' => $this->awayLine($professional),
            ])->values()->all(),
        ];
    }

    /** "Ausente hasta el 27/11" while they are away today, so the list says it without opening the sheet. */
    public function awayLine(Professional $professional): ?string
    {
        $today = CarbonImmutable::now($this->business->localTimezone());
        $closure = $professional->closures->first(fn (ProfessionalClosure $each): bool => $each->covers($today));

        return $closure === null ? null : __('professionals.list.away', ['until' => $closure->ends_on->translatedFormat('d/m')]);
    }

    /** What is still to come, said only when there is something: the number that decides whether they can be removed. */
    public function upcomingLine(Professional $professional): ?string
    {
        $count = (int) $professional->upcoming;

        return $count === 0 ? null : trans_choice('professionals.list.upcoming', $count, ['count' => $count]);
    }

    /**
     * The services a professional can be set to attend: the ones that take a booking.
     *
     * @var Collection<int, Service>
     */
    public Collection $bookableServices {
        get => (new Agenda($this->business))->bookableServices;
    }

    /** Whether there is anyone yet: the agenda behaves as a single one until there is. */
    public bool $hasAny {
        get => $this->business->professionals()->exists();
    }

    /**
     * What the sheet edits. Every bookable service is listed so each one can be
     * switched on; the ones they attend carry what they change.
     */
    public function sheetOf(?int $id = null): ProfessionalDto
    {
        $professional = $id === null ? null : $this->business->professionals()->with(['services', 'hours'])->findOrFail($id);
        $attended = $professional?->services->keyBy('id');

        $services = $this->bookableServices->mapWithKeys(fn (Service $service): array => [$service->id => [
            'attends' => $attended?->has($service->id) ?? false,
            'duration_minutes' => $this->text($attended?->get($service->id)?->pivot->duration_minutes),
            'price_type' => $attended?->get($service->id)?->pivot->price_type,
            'price' => $this->decimal($attended?->get($service->id)?->pivot->price),
            'deposit' => $this->decimal($attended?->get($service->id)?->pivot->deposit),
        ]])->all();

        if ($professional === null) {
            return new ProfessionalDto(services: $services);
        }

        return new ProfessionalDto(
            name: $professional->name,
            is_active: $professional->is_active,
            uses_business_hours: $professional->hours->isEmpty(),
            week: $this->weekOf($professional),
            appointment_capacity: $this->text($professional->appointment_capacity),
            appointments_per_day: $this->text($professional->appointments_per_day),
            services: $services,
        );
    }

    /** The week in one line, as the business's own is told; they may also follow it. */
    public function hoursLine(Professional $professional): string
    {
        if ($professional->hours->isEmpty()) {
            return __('professionals.list.business_hours');
        }

        return (new OpeningHours($this->weekOf($professional), $this->business->localTimezone()))->summary;
    }

    /**
     * The days they are away that are still ahead.
     *
     * @return Collection<int, ProfessionalClosure>
     */
    public function closuresOf(int $id): Collection
    {
        return $this->business->professionals()->findOrFail($id)->closures()->upcoming()->get();
    }

    /**
     * Loads their time away — unless bookings are still standing inside it, which are
     * never cancelled behind anyone's back: they are counted and the owner moves them.
     *
     * @return array{added: bool, booked: int} Not added with `booked` 0 means those days were already loaded.
     */
    public function addClosure(int $id, string $range, ?string $reason): array
    {
        $professional = $this->business->professionals()->findOrFail($id);
        [$from, $to] = Closures::split($range);
        $timezone = $this->business->localTimezone();
        $booked = $professional->upcomingBetween(CarbonImmutable::parse($from, $timezone), CarbonImmutable::parse($to, $timezone));

        if ($booked > 0) {
            return ['added' => false, 'booked' => $booked];
        }

        if ($professional->closures()->whereDate('starts_on', '<=', $to)->whereDate('ends_on', '>=', $from)->exists()) {
            return ['added' => false, 'booked' => 0];
        }

        $closure = new ProfessionalClosure(['starts_on' => $from, 'ends_on' => $to, 'reason' => $reason === null || trim($reason) === '' ? null : trim($reason)]);
        $closure->business()->associate($this->business);
        $closure->professional()->associate($professional)->save();

        return ['added' => true, 'booked' => 0];
    }

    public function removeClosure(int $id, int $closureId): void
    {
        $this->business->professionals()->findOrFail($id)->closures()->findOrFail($closureId)->delete();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function save(array $validated, ?int $id = null): Professional
    {
        return app(SaveProfessional::class)->handle($this->business, $validated, $id);
    }

    /**
     * @throws RuntimeException when bookings are still to come
     */
    public function delete(int $id): void
    {
        app(DeleteProfessional::class)->handle($this->business->professionals()->findOrFail($id));
    }

    /**
     * The first shift of each day: the sheet edits one per day, which is all
     * the sheet itself ever writes.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private function weekOf(Professional $professional): array
    {
        return $professional->hours
            ->unique('day_of_week')
            ->mapWithKeys(fn ($shift): array => [$shift->day_of_week => [substr((string) $shift->opens_at, 0, 5), substr((string) $shift->closes_at, 0, 5)]])
            ->all();
    }

    private function text(int|string|null $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    /** The decimal cast prints "12000.00"; it was typed "12000" and should be found that way. */
    private function decimal(int|float|string|null $value): ?string
    {
        return $value === null ? null : rtrim(rtrim((string) $value, '0'), '.');
    }
}
