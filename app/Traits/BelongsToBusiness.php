<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Business;
use App\Services\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Isolates the model per business: it scopes every query to the current one
 * AND fills `business_id` on create. Both halves are needed — without the
 * second, a row is born ownerless and invisible to everyone.
 *
 * It only scopes when there IS a current business. No business means no
 * filter, on purpose: that is the admin and the background jobs. {@see Tenant}
 */
trait BelongsToBusiness
{
    public static function bootBelongsToBusiness(): void
    {
        static::addGlobalScope('business', function (Builder $builder): void {
            $businessId = app(Tenant::class)->id();

            if ($businessId === null) {
                return;
            }

            // qualifyColumn: without the table name, a join against another table
            // that also has `business_id` leaves the query ambiguous.
            $builder->where($builder->getModel()->qualifyColumn('business_id'), $businessId);
        });

        static::creating(function (Model $model): void {
            if ($model->getAttribute('business_id') === null) {
                $model->setAttribute('business_id', app(Tenant::class)->id());
            }
        });
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
