<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\DB;

/**
 * Takes a plan off sale without touching anyone on it.
 *
 * Archived is not deleted: the businesses holding it keep it, with its price
 * and its caps, and `Plan::named()` still finds it by its code. Only what is
 * offered changes. Two plans cannot be archived: the one that carries the free
 * trial (a new business would have nothing to start on) and the last one sold.
 */
class ArchivePlan
{
    public const string ARCHIVED = 'archived';

    public const string HAS_TRIAL = 'has_trial';

    public const string LAST_SOLD = 'last_sold';

    public const string MISSING = 'missing';

    /** @return string One of the constants above: what happened. */
    public function handle(int $id): string
    {
        return DB::transaction(function () use ($id): string {
            $plan = SubscriptionPlan::query()->lockForUpdate()->find($id);

            if ($plan === null) {
                return self::MISSING;
            }

            if ($plan->archived_at !== null) {
                return self::ARCHIVED;
            }

            if ($plan->trial_days !== null) {
                return self::HAS_TRIAL;
            }

            $stillSold = SubscriptionPlan::query()->whereNull('archived_at')->whereKeyNot($id)->count();

            if ($stillSold === 0) {
                return self::LAST_SOLD;
            }

            // A badge on a card nobody can see would still be "the most chosen".
            $plan->forceFill(['archived_at' => now(), 'is_featured' => false])->save();

            return self::ARCHIVED;
        });
    }
}
