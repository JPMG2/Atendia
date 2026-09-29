<?php

declare(strict_types=1);

namespace App\Actions\Agenda;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;

class CancelAppointment
{
    /** Cancelling frees the hour and keeps the row: the assistant may have to explain it. */
    public function handle(Appointment $appointment): Appointment
    {
        $appointment->update(['status' => AppointmentStatus::Cancelled]);

        return $appointment;
    }
}
