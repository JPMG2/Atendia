<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubscriptionStatus;
use App\Traits\BelongsToBusiness;
use Carbon\CarbonInterface;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
            'status' => SubscriptionStatus::class,
        ];
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

        return $this->billing_cycle === 'yearly' ? $end->copy()->subYear() : $end->copy()->subMonth();
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

    public function isPaused(): bool
    {
        return $this->status === SubscriptionStatus::Paused;
    }

    /** What the next period costs: the plan price, or ten months for a year. */
    public function nextAmount(): float
    {
        $price = (float) config("atendia.plans.{$this->plan}.price");

        return $this->billing_cycle === 'yearly' ? $price * 10 : $price;
    }
}
