<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use App\Models\BusinessClosure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessClosure>
 */
class BusinessClosureFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'starts_on' => now()->addWeek()->format('Y-m-d'),
            'ends_on' => now()->addWeek()->format('Y-m-d'),
            'reason' => null,
        ];
    }

    /** A stretch of days, the way a holiday week is loaded. */
    public function days(int $count): static
    {
        return $this->state(fn (array $attributes): array => [
            'ends_on' => now()->addWeek()->addDays($count - 1)->format('Y-m-d'),
        ]);
    }
}
