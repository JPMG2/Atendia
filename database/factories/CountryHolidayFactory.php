<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Country;
use App\Models\CountryHoliday;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CountryHoliday>
 */
class CountryHolidayFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'country_id' => Country::factory(),
            'name' => 'Feriado '.$this->faker->unique()->word(),
            'month' => 7,
            'day' => 5,
            'is_active' => true,
        ];
    }

    /** "The second Sunday of May": the shape counted inside a month. */
    public function nth(): static
    {
        return $this->state(fn (): array => ['month' => 5, 'day' => null, 'weekday' => 0, 'nth' => 2]);
    }
}
