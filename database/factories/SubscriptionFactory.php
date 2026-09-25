<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Classes\Main\Plan;
use App\Models\Business;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'plan' => 'emprende',
            'trial_ends_at' => null,
        ];
    }

    public function trial(): static
    {
        return $this->state(fn (): array => [
            'plan' => Plan::trial()->code,
            'trial_ends_at' => now()->addDays((int) Plan::trial()->trialDays),
        ]);
    }

    public function expiredTrial(): static
    {
        return $this->state(fn (): array => [
            'plan' => Plan::trial()->code,
            'trial_ends_at' => now()->subDay(),
        ]);
    }
}
