<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\SubscriptionPlan;

/** Puts an archived plan back on sale, as it was left. */
class RestorePlan
{
    public function handle(int $id): ?SubscriptionPlan
    {
        $plan = SubscriptionPlan::query()->find($id);

        $plan?->forceFill(['archived_at' => null])->save();

        return $plan;
    }
}
