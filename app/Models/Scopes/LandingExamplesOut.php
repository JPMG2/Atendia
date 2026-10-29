<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Services\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * The landing's example businesses are the hero's animation, not customers:
 * no list, figure, job or file of the platform may see them. The model says
 * it once, so no caller can forget it. A query that names `is_demo` itself
 * (the landing asking for its examples) decides on its own.
 */
class LandingExamplesOut implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $asksForExamples = collect($builder->getQuery()->wheres)
            ->contains(fn (array $where): bool => in_array($where['column'] ?? null, ['is_demo', $model->qualifyColumn('is_demo')], true));

        if ($asksForExamples) {
            return;
        }

        // Inside an example's own tenant (its chat, its jobs) that one business exists for itself.
        $tenant = app(Tenant::class)->id();

        $builder->where(fn (Builder $businesses): Builder => $businesses
            ->where($model->qualifyColumn('is_demo'), false)
            ->when($tenant !== null, fn (Builder $own): Builder => $own->orWhere($model->getQualifiedKeyName(), $tenant)));
    }
}
