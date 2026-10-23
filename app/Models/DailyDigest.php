<?php

declare(strict_types=1);

namespace App\Models;

use App\Dto\DailyDigestDto;
use App\Traits\BelongsToBusiness;
use Database\Factories\DailyDigestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One business's end-of-day read, kept after the mail that carried it left.
 * Written only by the closing digest, so a day that never mailed has no row.
 */
#[Fillable(['business_id', 'for_day', 'messages', 'contacts', 'summary', 'questions', 'referred', 'appointments_tomorrow'])]
class DailyDigest extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<DailyDigestFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'for_day' => 'date',
            'messages' => 'integer',
            'contacts' => 'integer',
            'questions' => 'array',
            'referred' => 'integer',
            'appointments_tomorrow' => 'integer',
        ];
    }

    /** Keeps the read of a day once: a second run the same day replaces it, never doubles it. */
    public static function keep(Business $business, DailyDigestDto $digest): self
    {
        return self::query()->updateOrCreate(
            ['business_id' => $business->id, 'for_day' => now($business->localTimezone())->toDateString()],
            [
                'messages' => $digest->messages,
                'contacts' => $digest->contacts,
                'summary' => $digest->summary,
                'questions' => $digest->questions,
                'referred' => $digest->referred,
                'appointments_tomorrow' => $digest->appointmentsTomorrow,
            ],
        );
    }
}
