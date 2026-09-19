<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToBusiness;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The business's plan row. The effective plan is resolved by
 * App\Classes\Main\Plan: an expired trial falls to the floor plan there,
 * so this row never needs a downgrade job.
 */
#[Fillable(['business_id', 'plan', 'trial_ends_at'])]
class Subscription extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
        ];
    }

    public function onTrial(): bool
    {
        return $this->trial_ends_at !== null && $this->trial_ends_at->isFuture();
    }

    /** Whole days left of the trial, never zero while it runs; null when off trial. */
    public function trialDaysLeft(): ?int
    {
        return $this->onTrial()
            ? max(1, (int) ceil(now()->diffInHours($this->trial_ends_at) / 24))
            : null;
    }
}
