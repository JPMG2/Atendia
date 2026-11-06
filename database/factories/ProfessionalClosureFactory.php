<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use App\Models\Professional;
use App\Models\ProfessionalClosure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProfessionalClosure>
 */
class ProfessionalClosureFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->addWeek();

        return [
            'business_id' => Business::factory(),
            'professional_id' => Professional::factory(),
            'starts_on' => $start->format('Y-m-d'),
            'ends_on' => $start->copy()->addDays(6)->format('Y-m-d'),
            'reason' => null,
        ];
    }
}
