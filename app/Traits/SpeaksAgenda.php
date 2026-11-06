<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Professional;
use App\Models\Service;
use App\Services\Agenda\SlotFinder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * What the agenda skills say out loud. Shared so the hours the assistant
 * OFFERS read exactly like the ones it CONFIRMS.
 */
trait SpeaksAgenda
{
    /** "lunes 6/10 09:00", on the business's clock and in its language. */
    protected function slotLabel(CarbonImmutable $slot): string
    {
        return $this->dayLabel($slot).$slot->format(' H:i');
    }

    /**
     * "lunes 6/10": the day with no hour glued to it. Naming a day with
     * slotLabel read as "el lunes 6/10 00:00" — an hour nobody meant.
     */
    protected function dayLabel(CarbonImmutable $day): string
    {
        return $day->locale(app()->getLocale())->translatedFormat('l j/n');
    }

    /** @param  list<CarbonImmutable>  $slots */
    protected function slotList(array $slots): string
    {
        return implode(', ', array_map(fn (CarbonImmutable $slot): string => $this->slotLabel($slot), $slots));
    }

    protected function appointmentLabel(Appointment $appointment, Business $business): string
    {
        $when = $this->slotLabel($appointment->starts_at->setTimezone($business->localTimezone()));

        return "#{$appointment->id} {$when}"
            .($appointment->service === null ? '' : " ({$appointment->service->name})")
            .($appointment->professional === null ? '' : " con {$appointment->professional->name}");
    }

    /**
     * The professional the customer named, matched loosely like a service, among
     * those who can attend it; null when no name was given or none matches.
     */
    protected function professionalNamed(Business $business, string $name, ?Service $service): ?Professional
    {
        $wanted = Str::lower(Str::ascii(trim($name)));

        if ($wanted === '') {
            return null;
        }

        return Professional::offeredFor($business, $service)
            ->first(fn (Professional $professional): bool => str_contains(Str::lower(Str::ascii($professional->name)), $wanted)
                || str_contains($wanted, Str::lower(Str::ascii($professional->name))));
    }

    /** " Atienden: Ana Soto, Luis Paz." for a business with people set up for the service; nothing otherwise. */
    protected function attendingLine(Business $business, ?Service $service): string
    {
        $names = Professional::offeredFor($business, $service)->pluck('name');

        return $names->isEmpty() ? '' : ' Atienden: '.$names->implode(', ').'.';
    }

    /**
     * How long the slot lasts: the person's own length, or — with nobody named — the one all of
     * them share, or the range when they differ (the hour is the same, the end is not).
     */
    protected function durationLabel(Business $business, ?Service $service, ?Professional $professional): string
    {
        $finder = app(SlotFinder::class);
        $offered = $professional === null ? Professional::offeredFor($business, $service) : collect([$professional]);
        $minutes = $offered->isEmpty()
            ? [$finder->slotMinutes($business, $service)]
            : $offered->map(fn (Professional $each): int => $finder->slotMinutes($business, $service, $each))->unique()->sort()->values()->all();

        return count($minutes) === 1 ? "duran {$minutes[0]} min" : 'duran entre '.min($minutes).' y '.max($minutes).' min';
    }

    /**
     * The bookable service the customer named, matched loosely (case, accents)
     * against THIS business's own list; null means a plain slot.
     */
    protected function bookableService(Business $business, string $name): ?Service
    {
        $wanted = Str::lower(Str::ascii(trim($name)));

        if ($wanted === '') {
            return null;
        }

        return $business->services()
            ->where('is_active', true)
            ->where('is_bookable', true)
            ->get(['id', 'name', 'duration_minutes'])
            ->first(fn (Service $service): bool => str_contains(Str::lower(Str::ascii($service->name)), $wanted)
                || str_contains($wanted, Str::lower(Str::ascii($service->name))));
    }
}
