<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToBusiness;
use Carbon\CarbonInterface;
use Database\Factories\ProfessionalClosureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A stretch of days one professional does not attend, whatever their weekly
 * shifts say. One day is a range of one, like {@see BusinessClosure}.
 *
 * `business_id` stays out of Fillable on purpose: it is the tenant boundary.
 */
#[Fillable(['starts_on', 'ends_on', 'reason'])]
class ProfessionalClosure extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<ProfessionalClosureFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /** Whether the day falls inside the stretch, both ends included. */
    public function covers(CarbonInterface $day): bool
    {
        $date = $day->format('Y-m-d');

        return $this->starts_on->format('Y-m-d') <= $date && $this->ends_on->format('Y-m-d') >= $date;
    }

    /**
     * From today on, soonest first: what is still ahead is all the sheet shows.
     *
     * @param  Builder<self>  $query
     */
    public function scopeUpcoming(Builder $query): void
    {
        $query->whereDate('ends_on', '>=', now()->format('Y-m-d'))->orderBy('starts_on');
    }

    /** One day, or the range spelled out as the screens write it. */
    public function label(): string
    {
        $from = $this->starts_on->translatedFormat('d/m/Y');

        return $this->starts_on->isSameDay($this->ends_on)
            ? $from
            : $from.' – '.$this->ends_on->translatedFormat('d/m/Y');
    }

    /**
     * @return BelongsTo<Professional, $this>
     */
    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }
}
