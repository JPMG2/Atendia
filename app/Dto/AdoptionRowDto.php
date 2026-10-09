<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enums\AdoptionSituation;
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
        public readonly ?int $businessId = null,
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

    /** Whole days on the step it is on: what the "stalled" rule measures. */
    public int $daysInStep {
        get => (int) ($this->reachedAt($this->step) ?? $this->registeredAt)
            ->startOfDay()
            ->diffInDays(CarbonImmutable::now()->startOfDay());
    }

    /** The days this step is allowed before the account counts as stalled: a platform setting per step. */
    public int $stallLimit {
        get => (int) config('atendia.adoption.stall_days.'.$this->step->value, 7);
    }

    /**
     * Answered is done; a step held longer than the platform setting is stalled;
     * anything younger has not had time to stall and is still getting started.
     */
    public AdoptionSituation $situation {
        get => match (true) {
            $this->step === AdoptionStep::AssistantAnswered => AdoptionSituation::Active,
            $this->daysInStep >= $this->stallLimit => AdoptionSituation::Stalled,
            default => AdoptionSituation::Starting,
        };
    }

    /** Short of the last step is exactly where the product loses people. */
    public bool $isStalled {
        get => $this->step !== AdoptionStep::AssistantAnswered;
    }
}
