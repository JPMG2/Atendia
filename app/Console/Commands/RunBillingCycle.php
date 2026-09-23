<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Mail\BillingReminder;
use App\Messaging\Channels\Email;
use App\Messaging\Channels\WhatsApp;
use App\Messaging\WhatsApp\BillingReminder as BillingReminderMessage;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * The daily billing pass: reminds at 10 and 5 days, walks a lapsed period
 * through the grace days (reminding each day) and pauses the assistant
 * when they run out. A receipt waiting for review freezes all of it — the
 * client already paid, the delay is ours.
 */
class RunBillingCycle extends Command
{
    protected $signature = 'atendia:billing-cycle';

    protected $description = 'Payment reminders, grace days and pausing unpaid assistants';

    public function handle(): int
    {
        $subscriptions = Subscription::query()
            ->with('business')
            ->whereNotNull('current_period_ends_at')
            ->where('status', '!=', SubscriptionStatus::Paused)
            ->get();

        foreach ($subscriptions as $subscription) {
            $business = $subscription->business;

            if ($business === null || $business->payments()->where('status', PaymentStatus::Pending)->exists()) {
                continue;
            }

            $this->advance($subscription);
        }

        return self::SUCCESS;
    }

    private function advance(Subscription $subscription): void
    {
        $days = (int) $subscription->daysUntilPayment();

        if ($days > 0) {
            if (in_array($days, (array) config('atendia.billing.reminder_days'), true)) {
                $this->remindOnce($subscription, 'upcoming', $days);
            }

            return;
        }

        $graceLeft = (int) config('atendia.billing.grace_days') + $days;

        if ($graceLeft > 0) {
            $subscription->forceFill(['status' => SubscriptionStatus::PastDue])->save();
            $this->remindOnce($subscription, 'overdue', $graceLeft);

            return;
        }

        $subscription->forceFill(['status' => SubscriptionStatus::Paused, 'paused_at' => now()])->save();
        $this->remindOnce($subscription, 'paused', 0);
        $this->info("Paused {$subscription->business->name}");
    }

    /** Keyed to the period and the day: a rerun the same day never repeats a message. */
    private function remindOnce(Subscription $subscription, string $stage, int $days): void
    {
        $key = sprintf('billing:%d:%s:%s:%d', $subscription->id, $subscription->periodEndsAt()?->format('Ymd'), $stage, $days);

        if (! Cache::add($key, true, now()->addDays(40))) {
            return;
        }

        $business = $subscription->business;

        if (filled($business->billing_email)) {
            (new Email($business, [$business->billing_email], BillingReminder::class, [$stage, $days]))->send();
        }

        if (strlen($business->ownerWhatsAppDigits()) >= 8) {
            (new WhatsApp($business, [$business->ownerWhatsAppDigits()], BillingReminderMessage::class, [$stage, $days]))->send();
        }
    }
}
