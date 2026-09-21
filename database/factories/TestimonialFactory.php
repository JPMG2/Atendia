<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TestimonialStatus;
use App\Models\Business;
use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Testimonial>
 */
class TestimonialFactory extends Factory
{
    protected $model = Testimonial::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'quote' => fake()->sentence(10),
            'rating' => fake()->numberBetween(4, 5),
            'display_name' => fake()->company(),
            'display_role' => fake()->word(),
            'consent_given_at' => now(),
            'status' => TestimonialStatus::Pending,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => ['status' => TestimonialStatus::Approved]);
    }

    public function dismissed(): static
    {
        return $this->state(fn (): array => ['quote' => null, 'rating' => null, 'consent_given_at' => null, 'status' => TestimonialStatus::Dismissed]);
    }
}
