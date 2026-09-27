<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Department> */
class DepartmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'name' => fake()->unique()->randomElement(['Ventas', 'Pagos', 'Turnos', 'Reclamos', 'Resultados']),
            'routing_hint' => 'Quiere comprar, pide una cotización o un precio especial.',
            'hours' => null,
        ];
    }
}
