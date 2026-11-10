<?php

declare(strict_types=1);

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\Company;
use App\Services\Tenant;
use Illuminate\Http\Response;

/**
 * The booked hour as an .ics file, handed out by the public booking page right after
 * the booking. Signed and without a session: the signature is what keeps one person's
 * link from walking to another booking by changing the number.
 */
class CalendarFileController extends Controller
{
    public function __invoke(string $code, int $appointment): Response
    {
        $business = Business::forBookingCode($code);

        abort_if($business === null || $business->isSuspended(), 404);

        $booking = app(Tenant::class)->for($business->id, fn (): ?Appointment => Appointment::forCalendarFile($business, $appointment));

        abort_if($booking === null, 404);

        $file = $booking->toCalendarFile(__('agenda.public.calendar_event', ['business' => $business->name]), Company::brand());

        return response($file, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="turno.ics"',
        ]);
    }
}
