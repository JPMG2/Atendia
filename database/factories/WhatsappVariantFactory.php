<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SeasonalWindow;
use App\Models\WhatsappVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsappVariant>
 */
class WhatsappVariantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'seasonal_window_id' => SeasonalWindow::factory(),
            'phone' => null,
            'opening_schedule' => null,
            'whatsapp_greeting' => null,
            'is_active' => true,
        ];
    }
}
