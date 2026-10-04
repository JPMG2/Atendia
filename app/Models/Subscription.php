<?php

declare(strict_types=1);

namespace App\Models;

use App\Classes\Main\Plan;
use App\Enums\SubscriptionStatus;
use App\Traits\BelongsToBusiness;
use Carbon\CarbonInterface;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * The business's plan row. The effective plan is resolved by
 * App\Classes\Main\Plan: an expired trial falls to the floor plan there,
 * so this row never needs a downgrade job.
 */
#[Fillable(['business_id', 'plan', 'trial_ends_at', 'billing_cycle', 'current_period_ends_at'])]
class Subscription extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    /** Mirrors the column defaults, so a row created in this request already knows them. */
    protected $attributes = [
        'status' => 'trialing',
        'billing_cycle' => 'monthly',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'current_period_ends_at' => 'datetime',
            'paused_at' => 'datetime',
            'canceled_at' => 'datetime',
            'status' => SubscriptionStatus::class,
        ];
    }

    /**
     * What the platform charges next, within the window: the renewals the admin
     * should expect money for. Ordered by date, so the nearest is read first.
     *
     * @return Collection<int, Subscription>
     */
    public static function renewingWithin(int $days): Collection
    {
        return self::query()
            ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::Trialing])
            ->whereNotNull('current_period_ends_at')
            ->whereBetween('current_period_ends_at', [now(), now()->addDays($days)])
            ->with('business')
            ->orderBy('current_period_ends_at')
            ->get();
    }

    /**
     * The ones that did not pay: inside the grace days, or already silenced.
     * One list because the admin acts on both the same way — she calls them.
     *
     * @return Collection<int, Subscription>
     */
    public static function struggling(): Collection
    {
        return self::query()
            ->whereIn('status', [SubscriptionStatus::PastDue, SubscriptionStatus::Paused])
            ->with('business')
            ->orderBy('current_period_ends_at')
            ->get();
    }

    /** Owes a period: inside the grace days, or already silenced for not paying. */
    public function isBehind(): bool
    {
        return in_array($this->status, [SubscriptionStatus::PastDue, SubscriptionStatus::Paused], true);
    }

    /**
     * Asked to leave, still being served: she paid for the period, so it runs
     * to its end. Derived on purpose — a second status would need a job at
     * midnight and could drift from the date that actually decides it, the
     * same reason an expired trial is not downgraded by a job either.
     */
    public function isCanceling(): bool
    {
        return $this->canceled_at !== null && ! $this->hasEnded();
    }

    /** The cancellation ran its course: no grace, no pause, it is over. */
    public function hasEnded(): bool
    {
        return $this->canceled_at !== null
            && $this->periodEndsAt() !== null
            && $this->periodEndsAt()->isPast();
    }

    /**
     * The ones leaving but still served, nearest departure first. What the
     * admin needs is the money she is about to stop receiving, and when.
     *
     * @return Collection<int, Subscription>
     */
    public static function scheduledCancellations(): Collection
    {
        return self::query()
            ->whereNotNull('canceled_at')
            ->where('current_period_ends_at', '>=', now())
            ->with('business')
            ->orderBy('current_period_ends_at')
            ->get();
    }

    /** How many subscriptions the recurring revenue is actually made of. */
    public static function payingCount(): int
    {
        return self::query()
            ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::PastDue])
            ->count();
    }

    /** How many are still on their trial: future revenue, not current. */
    public static function trialingCount(): int
    {
        return self::query()->where('status', SubscriptionStatus::Trialing)->count();
    }

    /**
     * Monthly recurring revenue: what a month of the paying subscriptions is
     * worth, with a yearly cycle spread over its twelve months. Past due counts
     * — it is a customer inside the grace days, not a loss yet; a trial does
     * not, because nobody paid for it.
     */
    public static function monthlyRecurringRevenue(): float
    {
        return self::query()
            ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::PastDue])
            ->get(['plan', 'billing_cycle'])
            ->sum(function (Subscription $subscription): float {
                $plan = Plan::named($subscription->plan);

                return $subscription->billing_cycle === 'yearly'
                    ? $plan->yearlyPrice / 12
                    : (float) $plan->price;
            });
    }

    /** A paid subscription is off trial even if its trial date is still ahead. */
    public function onTrial(): bool
    {
        return $this->status === SubscriptionStatus::Trialing
            && $this->trial_ends_at !== null
            && $this->trial_ends_at->isFuture();
    }

    /** Whole days left of the trial, never zero while it runs; null when off trial. */
    public function trialDaysLeft(): ?int
    {
        return $this->onTrial()
            ? max(1, (int) ceil(now()->diffInHours($this->trial_ends_at) / 24))
            : null;
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** The date the next payment is due: the trial's end while on trial. */
    public function periodEndsAt(): ?CarbonInterface
    {
        return $this->current_period_ends_at ?? $this->trial_ends_at;
    }

    /** Where the running period began: the last credited payment, or the sign-up. */
    public function periodStartsAt(): CarbonInterface
    {
        $end = $this->periodEndsAt();

        if ($end === null || $this->status === SubscriptionStatus::Trialing) {
            return $this->created_at;
        }

        return $this->billing_cycle === 'yearly' ? $end->copy()->subYearNoOverflow() : $end->copy()->subMonthNoOverflow();
    }

    public function periodLengthDays(): int
    {
        $end = $this->periodEndsAt();

        return $end === null ? 0 : max(1, (int) round($this->periodStartsAt()->diffInDays($end)));
    }

    /** Whole days until the payment date; negative once it passed (the grace days). */
    public function daysUntilPayment(): ?int
    {
        $end = $this->periodEndsAt();

        return $end === null ? null : (int) ceil(now()->diffInHours($end, false) / 24);
    }

    public function daysUsed(): int
    {
        return min($this->periodLengthDays(), max(0, $this->periodLengthDays() - max(0, (int) $this->daysUntilPayment())));
    }

    /**
     * What moving up to $to costs TODAY: the price difference for the days
     * left of the running period — the renewal date never moves. Zero on
     * trial (nothing was paid) and for a move down (that one waits for the
     * period end, so no refund or credit ever exists).
     */
    public function upgradeCharge(Plan $to): float
    {
        $from = Plan::named($this->plan);
        $yearly = $this->billing_cycle === 'yearly';
        $difference = $yearly ? $to->yearlyPrice - $from->yearlyPrice : $to->price - $from->price;

        if ($this->status === SubscriptionStatus::Trialing || $difference <= 0) {
            return 0.0;
        }

        $length = $this->periodLengthDays();
        $left = min($length, max(0, (int) $this->daysUntilPayment()));

        return round($difference * $left / max(1, $length), 2);
    }

    public function isPaused(): bool
    {
        return $this->status === SubscriptionStatus::Paused;
    }

    /** What the next period costs: the plan's month, or its year (two months free). */
    public function nextAmount(): float
    {
        $plan = Plan::named($this->plan);

        return (float) ($this->billing_cycle === 'yearly' ? $plan->yearlyPrice : $plan->price);
    }
}
