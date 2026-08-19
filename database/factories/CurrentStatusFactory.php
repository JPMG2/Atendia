<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CurrentStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CurrentStatus>
 */
class CurrentStatusFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // `name` es UNIQUE en la tabla, así que tiene que serlo acá o dos
            // estados creados en el mismo test revientan contra la constraint.
            'name' => $this->faker->unique()->word(),
            // Una clave de CurrentStatus::COLORS; un valor libre no lo pinta el CSS.
            'color' => $this->faker->randomElement(CurrentStatus::COLORS),
        ];
    }
}
