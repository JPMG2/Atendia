<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\DB;

class UpdatePlan
{
    /**
     * @param  array<string, mixed>  $data  Already validated by PlanForm.
     */
    public function handle(int $id, array $data): SubscriptionPlan
    {
        return DB::transaction(function () use ($id, $data): SubscriptionPlan {
            $plan = SubscriptionPlan::query()->findOrFail($id);

            // "Más elegido" and the free trial belong to ONE plan at a time: giving
            // them to this one takes them from whoever had them, so the landing
            // never shows two badges and a new business never has two trials.
            foreach (['is_featured' => false, 'trial_days' => null] as $column => $off) {
                if ($data[$column]) {
                    SubscriptionPlan::query()->whereKeyNot($id)->each(fn (SubscriptionPlan $other): bool => $other->update([$column => $off]));
                }
            }

            $plan->update($data);

            return $plan;
        });
    }
}
