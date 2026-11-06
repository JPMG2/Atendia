<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Actions\Professionals\DeleteProfessional;
use App\Actions\Professionals\SaveProfessional;
use App\Dto\ProfessionalDto;
use App\Models\Business;
use App\Models\Professional;
use App\Models\Service;
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
        get => $this->business->professionals()->with(['services:id,name', 'hours'])->get();
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
