<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ServiceModality;
use App\Models\ServiceType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceType>
 */
class ServiceTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // `code` y `name` son únicos globales en la tabla. El rubro queda null:
        // es solo agrupación de la pantalla y un tipo puede no tener ninguno.
        return [
            'code' => $this->faker->unique()->lexify('tipo-????'),
            'name' => $this->faker->unique()->words(2, true),
            'description' => $this->faker->sentence(),
            'service_modality_id' => ServiceModality::factory(),
            'business_sector_id' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
