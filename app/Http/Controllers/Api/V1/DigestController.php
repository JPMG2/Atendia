<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DailyDigest;
use Illuminate\Http\JsonResponse;

/**
 * The owner's end-of-day reads, the ones the closing mail carried. Only the
 * days that mailed exist: a plan without the digest, or a quiet day, has none.
 */
class DigestController extends Controller
{
    /** Two weeks: what the screen lists, not the archive. */
    private const int DAYS = 14;

    public function __invoke(): JsonResponse
    {
        return response()->json(['data' => DailyDigest::latestDays(self::DAYS)
            ->map(fn (DailyDigest $digest): array => [
                'id' => $digest->id,
                'day' => $digest->for_day->toDateString(),
                'messages' => $digest->messages,
                'contacts' => $digest->contacts,
                'summary' => $digest->summary,
                'questions' => $digest->questions,
                'referred' => $digest->referred,
                'appointments_tomorrow' => $digest->appointments_tomorrow,
            ])
            ->values(),
        ]);
    }
}
