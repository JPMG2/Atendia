<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AdoptionMarkKind;
use App\Enums\AdoptionStep;
use App\Models\AdoptionMark;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdoptionMark>
 */
class AdoptionMarkFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'step' => AdoptionStep::Registered,
            'kind' => AdoptionMarkKind::Written,
        ];
    }
}
