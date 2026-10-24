<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Actions\Billing\CancelSubscription;
use App\Models\Business;
use App\Services\EvolutionApi;
use App\Services\PlatformWhatsApp;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * The platform owner removes a business: its bridge instance and phone go
 * free, its people cannot sign in, and the rows stay (soft delete) so the
 * decision can be reversed. The bridge goes FIRST: if it does not answer,
 * nothing local changes, because a freed row with a live instance behind it
 * is an orphan nobody can see.
 */
class RemoveBusiness
{
    public function __construct(
        private readonly EvolutionApi $evolution,
        private readonly CancelSubscription $cancelSubscription,
        private readonly ResolvePlatformEvent $resolveEvents,
    ) {}

    /**
     * @throws ConnectionException|RequestException when the bridge cannot release the instance
     */
    public function handle(Business $business): void
    {
        Gate::authorize('businesses.manage');

        $instance = $business->whatsapp_instance;

        // The platform's own instance is Aliadito's voice, not this business's: whatever the row says, it stays.
        if ($instance !== null && ! PlatformWhatsApp::owns($instance)) {
            $this->evolution->logout($instance);
            $this->evolution->deleteInstance($instance);
        }

        DB::transaction(function () use ($business): void {
            $subscription = $business->subscription;

            if ($subscription !== null && ! $subscription->isCanceling() && ! $subscription->hasEnded()) {
                $this->cancelSubscription->handle($subscription);
            }

            $business->forceFill([
                'whatsapp_instance' => null,
                'whatsapp_number' => null,
                'whatsapp_connected_at' => null,
            ])->save();

            // Same as closing an account: nobody of this business keeps a session.
            $business->users()->each(fn ($user) => $user->delete());

            $business->delete();

            // After the cancellation, which raises its own departure line: her removal is not news.
            $this->resolveEvents->ofBusiness($business->id);
        });
    }
}
