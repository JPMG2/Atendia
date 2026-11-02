<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Actions\Billing\CancelSubscription;
use App\Models\Business;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Undoes `RemoveBusiness`: the business and the people removed WITH it come
 * back. What it gave back to the bridge does not: the instance is gone, so
 * the owner links the phone again. A person who had closed their own account
 * long before is not brought back by someone else's restore.
 */
class RestoreBusiness
{
    /** Rows removed within this many seconds of the business went with it. */
    private const int TOGETHER = 60;

    public function __construct(private readonly CancelSubscription $cancelSubscription) {}

    public function handle(Business $business): void
    {
        Gate::authorize('businesses.manage');

        $removedAt = $business->deleted_at;

        if ($removedAt === null || $business->isErased()) {
            return;
        }

        DB::transaction(function () use ($business, $removedAt): void {
            $business->restore();

            // Brought back by the admin, the owner's old closing is void: it must not erase her later.
            $business->users()->onlyTrashed()
                ->where('deleted_at', '>=', $removedAt->copy()->subSeconds(self::TOGETHER))
                ->update(['deleted_at' => null, 'closed_at' => null]);

            $subscription = $business->refresh()->subscription;

            // The baja was part of the removal: if she asked for it herself
            // earlier, it stays asked.
            if ($subscription?->canceled_at !== null
                && $subscription->canceled_at->greaterThanOrEqualTo($removedAt->copy()->subSeconds(self::TOGETHER))) {
                $this->cancelSubscription->undo($subscription);
            }
        });
    }
}
