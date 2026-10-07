<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enums\IncidentKind;
use Carbon\CarbonImmutable;

/**
 * One thing that went wrong, ready to print. Aggregated and read-only.
 *
 * It carries the EVIDENCE, not a count: which business, which customer and
 * what the thread was left saying. A tally tells her something is broken; a
 * row tells her which customer to win back.
 */
class IncidentRowDto
{
    public function __construct(
        public readonly IncidentKind $kind,
        public readonly CarbonImmutable $happenedAt,
        public readonly ?string $business = null,
        public readonly ?int $businessId = null,
        public readonly ?string $customer = null,
        public readonly ?string $excerpt = null,
        public readonly ?int $conversationId = null,
    ) {}

    /** What the screen sorts by: severity first, and the freshest of each. */
    public int $weight {
        get => $this->kind->weight();
    }

    /**
     * Whole minutes it has been waiting. The screen prints it as the age of
     * the problem, which is what decides whether she acts now.
     */
    public int $minutesWaiting {
        get => (int) $this->happenedAt->diffInMinutes(CarbonImmutable::now());
    }
}
