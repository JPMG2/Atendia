<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PlatformEventKind;
use App\Enums\PlatformEventSeverity;
use App\Enums\PlatformEventState;
use App\Models\PlatformEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformEvent>
 */
class PlatformEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $kind = PlatformEventKind::BusinessJoined;
        $at = now()->subMinutes(fake()->numberBetween(1, 600));

        return [
            'kind' => $kind,
            'severity' => $kind->severity(),
            'state' => PlatformEventState::Open,
            'about_business_id' => null,
            'dedupe_key' => $kind->value.':'.fake()->unique()->numberBetween(1, 1_000_000),
            'detail' => null,
            'occurrences' => 1,
            'first_seen_at' => $at,
            'last_seen_at' => $at,
        ];
    }

    /** A kind carries its own severity: the state of a row never invents one. */
    public function ofKind(PlatformEventKind $kind): static
    {
        return $this->state(fn (): array => [
            'kind' => $kind,
            'severity' => $kind->severity(),
            'dedupe_key' => $kind->value.':'.fake()->unique()->numberBetween(1, 1_000_000),
        ]);
    }

    public function severity(PlatformEventSeverity $severity): static
    {
        return $this->state(fn (): array => ['severity' => $severity]);
    }

    public function acknowledged(): static
    {
        return $this->state(fn (): array => [
            'state' => PlatformEventState::Acknowledged,
            'acknowledged_at' => now(),
        ]);
    }

    public function resolved(): static
    {
        return $this->state(fn (): array => [
            'state' => PlatformEventState::Resolved,
            'resolved_at' => now(),
        ]);
    }
}
