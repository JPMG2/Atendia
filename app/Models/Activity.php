<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

/**
 * The audit trail as the platform reads it. The landing's examples are not
 * the platform's, so what was ever logged about them is not in its trail
 * either: the same rule `Business` keeps, said once for every reader.
 */
class Activity extends SpatieActivity
{
    protected static function booted(): void
    {
        static::addGlobalScope('landing_examples_out', function (Builder $builder): void {
            $builder->whereNot(fn (Builder $about): Builder => $about
                ->where($builder->getModel()->qualifyColumn('subject_type'), (new Business)->getMorphClass())
                ->whereIn($builder->getModel()->qualifyColumn('subject_id'), Business::demoIds()));
        });
    }
}
