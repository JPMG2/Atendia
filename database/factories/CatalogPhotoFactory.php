<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use App\Models\CatalogPhoto;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CatalogPhoto>
 */
class CatalogPhotoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $uuid = fake()->uuid();

        return [
            'business_id' => Business::factory(),
            'photoable_type' => (new Product)->getMorphClass(),
            'photoable_id' => Product::factory(),
            'disk' => 'public',
            'path' => "catalog/{$uuid}.jpg",
            'thumb_path' => "catalog/{$uuid}-thumb.webp",
            'bytes' => 170000,
        ];
    }
}
