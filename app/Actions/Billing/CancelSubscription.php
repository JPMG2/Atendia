<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Models\Subscription;

/**
 * A business asks to leave. Every rule about what that MEANS lives here and
 * nowhere else: today it runs to the end of the period already paid, with no
 * refund and no notice period, and the lawyer has not met yet. The date asked
 * is a fact and is stored; the rest is derived (`isCanceling()`), so nothing
 * has to run at midnight to keep it true.
 */
class CancelSubscription
{
    public function handle(Subscription $subscription): Subscription
    {
        $subscription->forceFill(['canceled_at' => now()])->save();

        return $subscription->refresh();
    }

    /** Undoing is one field: she asked, then changed her mind before leaving. */
    public function undo(Subscription $subscription): Subscription
    {
        $subscription->forceFill(['canceled_at' => null])->save();

        return $subscription->refresh();
    }
}
