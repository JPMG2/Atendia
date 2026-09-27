<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ModerationSeverity;
use App\Models\Business;
use App\Models\ModerationFlag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModerationFlag>
 */
class ModerationFlagFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'source' => 'logo',
            'kind' => 'image',
            'severity' => ModerationSeverity::Rejected,
            'category' => 'sexual',
            'score' => 0.62,
            'fingerprint' => hash('sha256', fake()->uuid()),
        ];
    }

    public function severe(): static
    {
        return $this->state(fn (): array => ['severity' => ModerationSeverity::Severe, 'category' => 'sexual/minors', 'score' => 0.97]);
    }
}
