<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ServiceAttribute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceAttribute>
 */
class ServiceAttributeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // `code` and `name` are globally unique on the table.
        return [
            'code' => $this->faker->unique()->lexify('atributo-????'),
            'name' => $this->faker->unique()->words(2, true),
            'description' => $this->faker->sentence(),
            'data_type' => 'text',
            'unit' => null,
            'options' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    /** A list attribute, the only type that uses the `options` column. */
    public function list(): self
    {
        return $this->state(fn (): array => [
            'data_type' => 'list',
            'options' => ['Chico', 'Mediano', 'Grande'],
        ]);
    }
}
