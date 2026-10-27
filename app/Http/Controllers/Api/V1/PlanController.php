<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Classes\Main\Client;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Mi plan" for the app: the plan card, this month's meters and the owner's
 * "Ask" quota, from the same piece the web screen reads. Read only: changing
 * the plan or leaving stays out until the meeting with the accountant (R-07,
 * R-11, R-15, R-17), so nothing here can do either.
 */
class PlanController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $usage = Client::for($request->user())->planUsage;

        // EnsureAppAccess already refused anyone without a business.
        abort_if($usage === null, 404);

        return response()->json(['data' => [
            // The screen's own words, so the phone speaks like the panel in every variant of Spanish.
            'copy' => [
                'sub' => __('plan.sub'),
                'current' => __('plan.current'),
            ],
            'plan' => $usage->card,
            'meters' => $usage->meters,
            'ask' => $usage->ask,
        ]]);
    }
}
