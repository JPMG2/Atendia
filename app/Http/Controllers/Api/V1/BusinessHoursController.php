<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Classes\Main\Client;
use App\Classes\Main\Schedule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SaveBusinessHoursRequest;
use App\Models\BusinessHour;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The "Horarios de atención" card of "Mi negocio", through the web's own
 * piece and Action: what the app saves is what the web would have saved,
 * knowledge sync included. The days off are {@see BusinessClosuresController}.
 */
class BusinessHoursController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->payload($this->schedule($request))]);
    }

    public function update(SaveBusinessHoursRequest $request, NotificationService $notifications): JsonResponse
    {
        $schedule = $this->schedule($request);
        $week = $schedule->normalized($request->validated('week'));

        if (! $schedule->differsFrom($week)) {
            return response()->json(['message' => __('notifications.no_changes'), 'data' => $this->payload($schedule)]);
        }

        $business = $schedule->save($week);

        return response()->json([
            'message' => $notifications->updatedRelated($business)->message,
            'data' => $this->payload($schedule),
        ]);
    }

    private function schedule(Request $request): Schedule
    {
        $schedule = Client::for($request->user())->schedule;

        // EnsureAppAccess already refused anyone without a business.
        abort_if($schedule === null, 404);

        return $schedule;
    }

    /**
     * Days as an ordered list, Monday first: a JSON object would be re-sorted
     * by the phone and Sunday would come first.
     *
     * @return array<string, mixed>
     */
    private function payload(Schedule $schedule): array
    {
        $names = BusinessHour::dayNames();

        return [
            'copy' => [
                'title' => __('client.business.hours.title'),
                'sub' => __('client.business.hours.sub'),
                'overnight_hint' => __('client.business.hours.overnight_hint'),
                'add_shift' => __('client.business.hours.add_shift'),
                'remove_shift' => __('client.business.hours.remove_shift'),
                'apply_weekdays' => __('client.business.hours.apply_weekdays'),
                'closed' => __('client.business.hours.closed'),
                'opens' => __('client.business.hours.opens'),
                'closes' => __('client.business.hours.closes'),
                'time_placeholder' => __('client.business.hours.time_placeholder'),
                'copy_to' => __('client.business.hours.copy_to'),
                'copy_apply' => __('client.business.hours.copy_apply'),
                'assistant_title' => __('client.business.hours.assistant_title'),
                'assistant_sub' => __('client.business.hours.assistant_sub'),
                'save' => __('client.business.actions.save'),
                'unsaved' => __('client.business.unsaved_pill'),
                'leave' => [
                    'title' => __('client.business.unsaved.title'),
                    'message' => __('client.business.unsaved.message'),
                    'accept' => __('client.business.unsaved.accept'),
                ],
            ],
            'days' => collect(Schedule::DAYS)->map(fn (int $day): array => [
                'day' => $day,
                'name' => $names[$day],
                'shifts' => $schedule->times[$day],
            ])->all(),
            // What the assistant will say about the week, empty until a shift is saved.
            'assistant' => $schedule->spoken,
        ];
    }
}
