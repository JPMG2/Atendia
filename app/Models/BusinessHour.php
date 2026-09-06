<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\BusinessHourFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * One opening shift of a business: a day can hold several rows (morning and
 * afternoon shifts). Times are local to the business's timezone.
 *
 * `business_id` stays out of Fillable on purpose: it is the tenant boundary
 * and is only ever set through the relation, like {@see Service}.
 */
#[Fillable(['day_of_week', 'opens_at', 'closes_at'])]
class BusinessHour extends Model
{
    /** @use HasFactory<BusinessHourFactory> */
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
     * Localized day names keyed by day-of-week, 0 = Sunday like date("w").
     *
     * Carbon translates them for the active locale: a hand-written list in
     * lang would dodge the translator and die on the next regional variant.
     *
     * @return array<int, string>
     */
    public static function dayNames(): array
    {
        // 2024-09-01 fell on a Sunday, so day-of-week maps straight onto it.
        return collect(range(0, 6))
            ->mapWithKeys(fn (int $day): array => [
                $day => Str::ucfirst(Carbon::create(2024, 9, $day + 1)->locale(app()->getLocale())->dayName),
            ])
            ->all();
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
