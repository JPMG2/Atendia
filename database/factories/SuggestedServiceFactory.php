<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BusinessActivity;
use App\Models\ServiceType;
use App\Models\SuggestedService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SuggestedService>
 */
class SuggestedServiceFactory extends Factory
{
    protected $model = SuggestedService::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_activity_id' => BusinessActivity::factory(),
            'service_type_id' => ServiceType::factory(),
            'name' => fake()->unique()->words(2, true),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
