<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ComplianceEventKind;
use App\Models\Business;
use App\Models\ComplianceEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ComplianceEvent>
 */
class ComplianceEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'kind' => ComplianceEventKind::ContentRejected,
            'detail' => 'sexual',
            'actor_id' => null,
            'occurred_at' => now(),
        ];
    }

    public function of(ComplianceEventKind $kind, ?string $detail = null): static
    {
        return $this->state(fn (): array => ['kind' => $kind, 'detail' => $detail]);
    }
}
