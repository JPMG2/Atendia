<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use App\Models\ProductImport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductImport>
 */
class ProductImportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'original_name' => 'inventario.xlsx',
            'path' => 'imports/inventario.xlsx',
            'mapping' => [['column' => 'Producto', 'target' => 'name']],
            'total_rows' => 10,
            'status' => 'pending',
        ];
    }
}
