<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use App\Models\DailyDigest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyDigest>
 */
class DailyDigestFactory extends Factory
{
    protected $model = DailyDigest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'for_day' => now()->toDateString(),
            'messages' => 12,
            'contacts' => 5,
            'summary' => 'Hoy preguntaron sobre turnos y precios.',
            'questions' => ['¿Atienden el sábado?', '¿Precio del perfil?'],
            'referred' => 0,
            'appointments_tomorrow' => 2,
        ];
    }
}
