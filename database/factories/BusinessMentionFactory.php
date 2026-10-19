<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MentionStatus;
use App\Models\Business;
use App\Models\BusinessMention;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessMention>
 */
class BusinessMentionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'goal_threshold' => 100,
            'conversations' => 120,
            'status' => MentionStatus::Pending,
            'business_account_url' => 'https://instagram.com/mi_negocio',
            'platform_accounts' => [['network' => 'Instagram', 'url' => 'https://instagram.com/aliadito']],
            'caption' => 'Un negocio ya atendió 100 conversaciones en un mes.',
            'answered_at' => now(),
        ];
    }
}
