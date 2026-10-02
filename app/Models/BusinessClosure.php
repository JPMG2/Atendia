<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToBusiness;
use Carbon\CarbonInterface;
use Database\Factories\BusinessClosureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A stretch of days the business keeps closed, whatever its weekly hours say.
 * One day is a range of one, so everything downstream asks the same question.
 *
 * `business_id` stays out of Fillable on purpose: it is the tenant boundary
 * and is only ever set through the relation, like {@see BusinessHour}.
 */
#[Fillable(['starts_on', 'ends_on', 'reason', 'opens_at', 'closes_at'])]
class BusinessClosure extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<BusinessClosureFactory> */
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

    /**
     * The closures that cover a given day — normally none, at most one.
     *
     * @param  Builder<self>  $query
     */
    public function scopeCovering(Builder $query, CarbonInterface $day): void
    {
        $date = $day->format('Y-m-d');

        $query->whereDate('starts_on', '<=', $date)->whereDate('ends_on', '>=', $date);
    }

    /**
     * From today on, soonest first: what is still ahead is all the screen
     * shows and all the assistant has to know about.
     *
     * @param  Builder<self>  $query
     */
    public function scopeUpcoming(Builder $query): void
    {
        $query->whereDate('ends_on', '>=', now()->format('Y-m-d'))->orderBy('starts_on');
    }

    /**
     * No hours means the door stays shut. With them the day is OPEN on these
     * instead of the weekly ones: the short Christmas Eve, not a holiday.
     */
    public function isFullDay(): bool
    {
        return $this->opens_at === null || $this->closes_at === null;
    }

    /** The special hours as the screen and the assistant write them. */
    public function hoursLabel(): ?string
    {
        return $this->isFullDay()
            ? null
            : substr((string) $this->opens_at, 0, 5).' – '.substr((string) $this->closes_at, 0, 5);
    }

    /** One day, or the range spelled out as the screen writes it. */
    public function label(): string
    {
        $from = $this->starts_on->translatedFormat('d/m/Y');

        return $this->starts_on->isSameDay($this->ends_on)
            ? $from
            : $from.' – '.$this->ends_on->translatedFormat('d/m/Y');
    }

    /**
     * What the assistant says when a customer asks about a closed day: the
     * reason when she wrote one, and nothing invented when she did not.
     *
     * @param  Collection<int, self>  $closures
     */
    public static function sentenceFor(Collection $closures): ?string
    {
        $closure = $closures->first();

        if ($closure === null) {
            return null;
        }

        $because = $closure->reason === null ? '' : ': '.$closure->reason;

        return $closure->isFullDay()
            ? 'El negocio no abre ese día'.$because.'.'
            : 'Ese día el negocio abre solo de '.$closure->hoursLabel().$because.'.';
    }
}
