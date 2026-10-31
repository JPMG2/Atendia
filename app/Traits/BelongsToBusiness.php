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
 * With no current business (the admin and the background jobs) it sees every
 * real business, never the landing's examples. {@see Tenant}
 */
trait BelongsToBusiness
{
    public static function bootBelongsToBusiness(): void
    {
        static::addGlobalScope('business', function (Builder $builder): void {
            $tenant = app(Tenant::class);
            $businessId = $tenant->id();

            // A signed-in client with no business yet (just registered) is not the admin: nothing at all.
            if ($businessId === null && ! $tenant->unrestricted()) {
                $builder->whereRaw('1 = 0');

                return;
            }

            // With no business adopted (the admin, a job, a platform figure) the landing's
            // examples are not there: their rows are seen only from inside their own tenant.
            // A row with no business (the platform's own AI spend) stays: NOT IN would drop a null.
            if ($businessId === null) {
                $column = $builder->getModel()->qualifyColumn('business_id');
                $builder->where(fn (Builder $rows): Builder => $rows->whereNull($column)->orWhereNotIn($column, Business::demoIds()));

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
