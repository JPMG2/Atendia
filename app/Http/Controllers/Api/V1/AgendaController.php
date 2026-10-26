<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Classes\Main\Agenda;
use App\Classes\Main\Client;
use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SaveBookingRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * "Agenda" for the app: the web's own day and week, the free hours, and every write through
 * the Actions the web calls. Owner only, as the web screen; a booking of another business
 * is a 404. Nothing here writes to the customer: the web's agenda does not either.
 */
class AgendaController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $query = $request->validate([
            'day' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'view' => ['sometimes', 'in:day,week'],
        ]);
        $agenda = $this->agenda($request);
        $day = isset($query['day']) ? CarbonImmutable::parse($query['day'], $request->user()->business->localTimezone()) : $agenda->today;

        return response()->json(['data' => ['copy' => $agenda->copy, ...$agenda->page($day, $query['view'] ?? 'day')]]);
    }

    /** The free hours of one day, for the sheet that re-asks when its day changes. */
    public function hours(Request $request): JsonResponse
    {
        $query = $request->validate([
            'day' => ['required', 'date_format:Y-m-d'],
            'keep' => ['sometimes', 'nullable', 'date_format:H:i'],
            'service' => ['sometimes', 'nullable', 'integer'],
        ]);
        $serviceId = isset($query['service']) ? (int) $query['service'] : null;

        return response()->json(['data' => ['hours' => array_values($this->agenda($request)->hoursFor($query['day'], $query['keep'] ?? null, $serviceId))]]);
    }

    /** A new booking's sheet (optionally with the hour a free-hour chip picked), or a booking's own, to move it. */
    public function sheet(Request $request): JsonResponse
    {
        $query = $request->validate([
            'day' => ['required', 'date_format:Y-m-d'],
            'time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'booking' => ['sometimes', 'nullable', 'integer'],
        ]);
        $agenda = $this->agenda($request);
        $booking = isset($query['booking']) ? $agenda->find((int) $query['booking']) : null;

        if ($booking !== null) {
            $this->open($agenda, $booking->id);
        }

        return response()->json(['data' => ['copy' => $agenda->copy, 'sheet' => $agenda->sheetOf($query['day'], $booking, $query['time'] ?? null)]]);
    }

    public function book(SaveBookingRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $this->agenda($request)->book([
                'customer_id' => (int) $validated['customer_id'],
                'service_id' => $validated['service_id'] === null ? null : (int) $validated['service_id'],
                'starts_at' => $validated['day'].' '.$validated['time'],
                'notes' => $validated['notes'],
            ]);
        } catch (RuntimeException) {
            // The hour went to someone else between the picker and the save: the web says so in its own words.
            throw ValidationException::withMessages(['time' => __('agenda.notify.slot_taken')]);
        }

        return response()->json(['message' => __('agenda.notify.booked'), 'data' => ['day' => $validated['day']]], 201);
    }

    public function move(SaveBookingRequest $request, int $booking): JsonResponse
    {
        $validated = $request->validated();
        $agenda = $this->agenda($request);
        $this->open($agenda, $booking);

        try {
            $agenda->reschedule($booking, $validated['day'].' '.$validated['time']);
        } catch (RuntimeException) {
            throw ValidationException::withMessages(['time' => __('agenda.notify.slot_taken')]);
        }

        return response()->json(['message' => __('agenda.notify.moved'), 'data' => ['day' => $validated['day']]]);
    }

    public function cancel(Request $request, int $booking): JsonResponse
    {
        $agenda = $this->agenda($request);
        $this->open($agenda, $booking);
        $agenda->cancel($booking);

        return response()->json(['message' => __('agenda.notify.cancelled')]);
    }

    public function done(Request $request, int $booking): JsonResponse
    {
        $agenda = $this->agenda($request);
        $this->open($agenda, $booking);
        $agenda->mark($booking, AppointmentStatus::Done);

        return response()->json(['message' => __('agenda.notify.marked')]);
    }

    /** The web offers its three actions only on a booking that still stands: there is nothing to do on any other. */
    private function open(Agenda $agenda, int $booking): void
    {
        abort_unless($agenda->find($booking)->status === AppointmentStatus::Confirmed, 404, __('notifications.not_found'));
    }

    private function agenda(Request $request): Agenda
    {
        $agenda = Client::for($request->user())->agenda;

        // EnsureAppAccess already refused anyone without a business.
        abort_if($agenda === null, 404);

        return $agenda;
    }
}
