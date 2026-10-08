<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SupportDelivery;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportTicketMessage>
 */
class SupportTicketMessageFactory extends Factory
{
    /**
     * An answer that reached the business, which is what most of a thread is.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'support_ticket_id' => SupportTicket::factory(),
            'business_id' => fn (array $attributes): int => SupportTicket::query()->findOrFail($attributes['support_ticket_id'])->business_id,
            'body' => fake()->sentence(10),
            'is_internal' => false,
            'delivery' => SupportDelivery::WhatsApp,
        ];
    }

    /** A note for the team: it has no delivery, because it goes nowhere. */
    public function note(): static
    {
        return $this->state(fn (): array => ['is_internal' => true, 'delivery' => null]);
    }
}
