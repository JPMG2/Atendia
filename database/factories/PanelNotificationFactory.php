<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PanelNotificationType;
use App\Models\Business;
use App\Models\PanelNotification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PanelNotification>
 */
class PanelNotificationFactory extends Factory
{
    protected $model = PanelNotification::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'type' => PanelNotificationType::CustomerWaiting,
            'dedupe_key' => 'waiting:'.fake()->unique()->numberBetween(1, 100000),
            'payload' => ['name' => fake()->firstName(), 'minutes' => 25],
            'url' => '/conversaciones',
        ];
    }

    public function handedToTeam(): self
    {
        return $this->state(fn (): array => [
            'type' => PanelNotificationType::HandedToTeam,
            'dedupe_key' => 'handoff:'.fake()->unique()->numberBetween(1, 100000),
            'payload' => ['name' => fake()->firstName()],
        ]);
    }

    public function appointmentBooked(): self
    {
        return $this->state(fn (): array => [
            'type' => PanelNotificationType::AppointmentBooked,
            'dedupe_key' => 'booking:'.fake()->unique()->numberBetween(1, 100000),
            'payload' => ['name' => fake()->firstName(), 'when' => '14:30', 'service' => 'Corte'],
            'url' => '/agenda',
        ]);
    }

    public function whatsAppDisconnected(): self
    {
        return $this->state(fn (): array => [
            'type' => PanelNotificationType::WhatsAppDisconnected,
            'dedupe_key' => 'whatsapp:down',
            'payload' => ['at' => '03:12'],
            'url' => '/whatsapp',
        ]);
    }
}
