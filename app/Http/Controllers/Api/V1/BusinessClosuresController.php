<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Business\ImportCountryHolidays;
use App\Classes\Main\Client;
use App\Classes\Main\Closures;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AddBusinessClosureRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Días que no abres" of the hours card, through the web's own piece and
 * Actions: a closure loaded here is the one the web would have loaded, and
 * telling the people who booked it is the same message from the same Action.
 */
class BusinessClosuresController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->payload($this->closures($request))]);
    }

    public function store(AddBusinessClosureRequest $request): JsonResponse
    {
        $closures = $this->closures($request);

        $added = $closures->add(
            (string) $request->validated('range'),
            $request->validated('reason'),
            $request->validated('opens_at'),
            $request->validated('closes_at'),
        );

        return $added
            ? $this->done($closures, __('client.business.hours.closure_added'), 'success')
            : $this->done($closures, __('client.business.hours.closure_exists'), 'info');
    }

    public function importHolidays(Request $request): JsonResponse
    {
        $closures = $this->closures($request);
        $added = app(ImportCountryHolidays::class)->handle($request->user()->business);

        abort_if($added === null, 404);

        return $added === 0
            ? $this->done($closures, __('client.business.hours.holidays_none'), 'info')
            : $this->done($closures, trans_choice('client.business.hours.holidays_added', $added, ['count' => $added]), 'success');
    }

    /** Cancels the hours held inside the closure and tells the people holding them: her call, never automatic. */
    public function tell(Request $request, int $closure): JsonResponse
    {
        $closures = $this->closures($request);

        abort_if($closures->find($closure) === null, 404);

        // A line that is down cannot tell anyone, so nothing is cancelled: the owner is told why and what to do.
        if (! $closures->canTell) {
            return response()->json(['message' => __('client.business.hours.closure_line_down')], 422);
        }

        $told = $closures->cancelAndTell($closure);

        abort_if($told === null, 404);

        return $this->done(
            $closures,
            trans_choice('client.business.hours.closure_notified', $told, ['count' => $told]),
            $told === 0 ? 'info' : 'success',
        );
    }

    public function destroy(Request $request, int $closure): JsonResponse
    {
        $closures = $this->closures($request);

        abort_unless($closures->remove($closure), 404);

        return $this->done($closures, __('client.business.hours.closure_removed'), 'success');
    }

    private function closures(Request $request): Closures
    {
        $closures = Client::for($request->user())->closures;

        // EnsureAppAccess already refused anyone without a business.
        abort_if($closures === null, 404);

        return $closures;
    }

    private function done(Closures $closures, string $message, string $kind): JsonResponse
    {
        return response()->json(['message' => $message, 'kind' => $kind, 'data' => $this->payload($closures)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Closures $closures): array
    {
        $holidays = $closures->holidaysAhead->count();

        return [
            'copy' => [
                'title' => __('client.business.hours.closures_title'),
                'sub' => __('client.business.hours.closures_sub'),
                'empty' => __('client.business.hours.closure_empty'),
                'from' => __('client.business.hours.closure_from'),
                'to' => __('client.business.hours.closure_to'),
                'date_hint' => __('client.business.hours.closure_date_hint'),
                'date_placeholder' => __('client.business.hours.date_placeholder'),
                'reason' => __('client.business.hours.closure_reason'),
                'reason_hint' => __('client.business.hours.closure_reason_hint'),
                'hours_hint' => __('client.business.hours.closure_hours_hint'),
                'opens' => __('client.business.hours.opens'),
                'closes' => __('client.business.hours.closes'),
                'time_placeholder' => __('client.business.hours.time_placeholder'),
                'add' => __('client.business.hours.closure_add'),
                'remove' => __('client.business.hours.closure_remove'),
                'tell' => __('client.business.hours.closure_notify'),
                'holidays_note' => __('client.business.hours.holidays_note'),
            ],
            // Only when the country has holidays loaded and some are still ahead: a button that would add nothing lies.
            'holidays' => $holidays === 0 ? null : [
                'count' => $holidays,
                'label' => trans_choice('client.business.hours.holidays_import', $holidays, ['count' => $holidays]),
            ],
            'closures' => $closures->upcoming->map(function ($closure) use ($closures): array {
                $booked = $closures->pendingFor($closure);

                return [
                    'id' => $closure->id,
                    'label' => $closure->label(),
                    'reason' => $closure->reason,
                    'full_day' => $closure->isFullDay(),
                    'hours' => $closure->hoursLabel(),
                    'booked' => $booked,
                    'booked_label' => $booked > 0 ? trans_choice('client.business.hours.closure_booked', $booked, ['count' => $booked]) : null,
                ];
            })->values()->all(),
        ];
    }
}
