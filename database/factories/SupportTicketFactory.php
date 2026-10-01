<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SupportTicketKind;
use App\Enums\SupportTicketStatus;
use App\Models\Business;
use App\Models\SupportTicket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportTicket>
 */
class SupportTicketFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'code' => SupportTicket::freshCode(),
            'kind' => SupportTicketKind::Problem,
            'status' => SupportTicketStatus::New,
            'screen' => 'my-products',
            'body' => fake()->sentence(12),
            'context' => ['url' => 'https://atendia.test/productos', 'viewport' => '1280x900'],
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn (): array => [
            'status' => SupportTicketStatus::Resolved,
            'resolved_at' => now(),
        ]);
    }
}
