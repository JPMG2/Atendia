<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Country;
use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Country>
 */
class CountryFactory extends Factory
{
    /**
     * The faker only has some 240 country names and repeated one in a long
     * run: `countries_name_unique` then killed two tests that had nothing to
     * do with countries. A sequence cannot repeat, and the padding keeps
     * alphabetical order equal to creation order for whoever asserts a list.
     */
    private static int $sequence = 0;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'currency_id' => Currency::factory(),
            'name' => 'País de prueba '.str_pad((string) ++self::$sequence, 3, '0', STR_PAD_LEFT),
            'code' => strtoupper($this->faker->unique()->lexify('???')),
            'iso2' => strtoupper($this->faker->unique()->lexify('??')),
            'phone_code' => (string) $this->faker->numberBetween(1, 999),
            'is_active' => true,
        ];
    }
}
