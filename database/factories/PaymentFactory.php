<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Business;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'plan' => 'negocio',
            'billing_cycle' => 'monthly',
            'amount' => 79,
            'currency' => 'USD',
            'method' => 'transfer',
            'reference' => (string) fake()->numerify('########'),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (): array => [])->afterMaking(function (Payment $payment): void {
            $payment->forceFill([
                'status' => PaymentStatus::Paid,
                'paid_at' => now(),
                'period_starts_at' => now(),
                'period_ends_at' => now()->addMonth(),
            ]);
        });
    }
}
