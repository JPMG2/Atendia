<?php

declare(strict_types=1);

namespace App\Actions\Agenda;

use App\Models\Business;

class SaveAgendaSettings
{
    /** @param  array{appointments_enabled: bool, appointment_capacity: int, appointments_per_day: ?int, appointment_slot_minutes: int}  $data  Already validated. */
    public function handle(Business $business, array $data): Business
    {
        $business->update([
            'appointments_enabled' => $data['appointments_enabled'],
            'appointment_capacity' => $data['appointment_capacity'],
            'appointments_per_day' => $data['appointments_per_day'],
            'appointment_slot_minutes' => $data['appointment_slot_minutes'],
        ]);

        return $business;
    }
}
