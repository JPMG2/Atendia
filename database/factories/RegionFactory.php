<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Province;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Region>
 */
class RegionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'province_id' => Province::factory(),
            // Único dentro de la provincia: el mismo nombre puede repetirse entre
            // provincias distintas, pero no dentro de una.
            //
            // El número sale de un espacio grande a propósito. Encadenar
            // `unique()->citySuffix()` agotaba el pool de faker (son un puñado de
            // sufijos) y a la vigésima región el factory tiraba OverflowException,
            // que en un test se lee como un bug del código y no de los datos.
            'name' => 'Zona '.$this->faker->unique()->numberBetween(1, 100000),
            'is_active' => true,
        ];
    }
}
