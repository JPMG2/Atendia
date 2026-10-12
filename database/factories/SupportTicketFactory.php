<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SupportTicketStatus;
use App\Models\Business;
use App\Models\SupportTicket;
use App\Models\SupportTicketKind;
use Database\Seeders\SupportTicketKindSeeder;
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
            'kind' => 'problem',
            'status' => SupportTicketStatus::New,
            'screen' => 'my-products',
            'body' => fake()->sentence(12),
            'context' => ['url' => 'https://atendia.test/productos', 'viewport' => '1280x900'],
        ];
    }

    /**
     * A ticket names its kind by code, and the kind is a row: make sure the
     * row exists, with what the seeder ships, so a bare factory ticket reads.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (SupportTicket $ticket): void {
            $code = (string) $ticket->getRawOriginal('kind') ?: (string) ($ticket->getAttributes()['kind'] ?? '');

            SupportTicketKind::query()->firstOrCreate(
                ['code' => $code],
                SupportTicketKindSeeder::DEFAULTS[$code] ?? ['name' => ucfirst($code)],
            );
        });
    }

    public function resolved(): static
    {
        return $this->state(fn (): array => [
            'status' => SupportTicketStatus::Resolved,
            'resolved_at' => now(),
        ]);
    }
}
