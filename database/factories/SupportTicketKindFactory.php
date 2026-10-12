<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SupportTicketKind;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportTicketKind>
 */
class SupportTicketKindFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $word = $this->faker->unique()->word();

        return [
            'code' => $word,
            'name' => ucfirst($word),
            'icon' => 'message-circle',
            'starting_priority' => 'normal',
            'runs_clock' => true,
            'sort_order' => 10,
            'is_active' => true,
        ];
    }
}
