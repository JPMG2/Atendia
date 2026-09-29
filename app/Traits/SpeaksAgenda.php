<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Service;
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
        return $slot->locale(app()->getLocale())->translatedFormat('l j/n H:i');
    }

    /** @param  list<CarbonImmutable>  $slots */
    protected function slotList(array $slots): string
    {
        return implode(', ', array_map(fn (CarbonImmutable $slot): string => $this->slotLabel($slot), $slots));
    }

    protected function appointmentLabel(Appointment $appointment, Business $business): string
    {
        $when = $this->slotLabel($appointment->starts_at->setTimezone($business->localTimezone()));

        return "#{$appointment->id} {$when}".($appointment->service === null ? '' : " ({$appointment->service->name})");
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
