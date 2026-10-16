<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Actions\Admin\RaisePlatformEvent;
use App\Actions\Admin\ResolvePlatformEvent;
use App\Enums\PlatformEventKind;
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

        $end = $subscription->periodEndsAt();
        app(RaisePlatformEvent::class)->handle(
            PlatformEventKind::BusinessCancelled,
            $subscription->business_id,
            $subscription->plan.($end === null ? '' : ' · '.$end->format('d/m/Y')),
        );

        return $subscription->refresh();
    }

    /** Undoing is one field: she asked, then changed her mind before leaving. */
    public function undo(Subscription $subscription): Subscription
    {
        $subscription->forceFill(['canceled_at' => null])->save();

        // The line would keep announcing a departure that no longer stands.
        app(ResolvePlatformEvent::class)->ofKind(PlatformEventKind::BusinessCancelled, $subscription->business_id);

        return $subscription->refresh();
    }
}
