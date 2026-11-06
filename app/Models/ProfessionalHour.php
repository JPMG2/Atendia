<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToBusiness;
use Database\Factories\ProfessionalHourFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One weekly shift of a professional, in the business's local time. Kept
 * apart from {@see BusinessHour}: that table also drives the "open now" clock
 * and when customers may be written to, which a doctor's shift must not move.
 *
 * `business_id` stays out of Fillable on purpose, same boundary as
 * {@see BusinessHour}.
 */
#[Fillable(['day_of_week', 'opens_at', 'closes_at'])]
class ProfessionalHour extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<ProfessionalHourFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Professional, $this>
     */
    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }
}
