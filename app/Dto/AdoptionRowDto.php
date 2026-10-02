<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enums\AdoptionStep;
use Carbon\CarbonImmutable;

/**
 * One account's progress for the admin's adoption screen: where it stalled,
 * how long since it came back, and whether it had to ask for help. Already
 * aggregated and read-only — the screen prints it and nothing else.
 */
class AdoptionRowDto
{
    public function __construct(
        public readonly string $owner,
        public readonly string $email,
        public readonly ?string $business,
        public readonly AdoptionStep $step,
        public readonly CarbonImmutable $registeredAt,
        public readonly ?CarbonImmutable $lastSeenAt,
        public readonly int $conversations,
        public readonly int $tickets,
        /** @var array<string, CarbonImmutable|null> When each rung was reached, keyed by step. */
        public readonly array $milestones = [],
    ) {}

    /** When this rung was reached, null when it never was. */
    public function reachedAt(AdoptionStep $step): ?CarbonImmutable
    {
        if ($step === AdoptionStep::Registered) {
            return $this->registeredAt;
        }

        return $this->milestones[$step->value] ?? null;
    }

    /**
     * Whole days from one rung to the next, and null unless BOTH happened: an
     * average built over half-walked paths is a number that lies.
     */
    public function daysBetween(AdoptionStep $from, AdoptionStep $to): ?int
    {
        $start = $this->reachedAt($from);
        $end = $this->reachedAt($to);

        return $start === null || $end === null ? null : (int) $start->diffInDays($end);
    }

    /** Whole days away. Null when she signed up and never signed in again. */
    public ?int $daysIdle {
        get => $this->lastSeenAt === null
            ? null
            : (int) $this->lastSeenAt->startOfDay()->diffInDays(CarbonImmutable::now()->startOfDay());
    }

    /** Short of the last step is exactly where the product loses people. */
    public bool $isStalled {
        get => $this->step !== AdoptionStep::AssistantAnswered;
    }
}
