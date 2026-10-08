<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How soon a report must be answered. It sets the clock: a customer who cannot
 * charge waits less than one who has an idea, and an idea waits for nobody.
 */
enum SupportTicketPriority: string
{
    case High = 'high';
    case Normal = 'normal';
    case Low = 'low';

    public function label(): string
    {
        return __('support.admin.priorities.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::High => 'is-danger',
            self::Normal => 'is-info',
            self::Low => 'is-neutral',
        };
    }

    /**
     * What a report starts with, from what the person said it was: a problem
     * stops somebody working, a question blocks a decision, an idea waits.
     */
    public static function startingFor(SupportTicketKind $kind): self
    {
        return match ($kind) {
            SupportTicketKind::Problem => self::High,
            SupportTicketKind::Question => self::Normal,
            SupportTicketKind::Idea => self::Low,
        };
    }

    /**
     * Hours a report of this priority may wait for our answer, or null when it
     * runs no clock. The two limits are platform settings, so she moves them
     * from the admin without a deploy.
     */
    public function overdueHours(): ?int
    {
        return match ($this) {
            self::High => (int) config('atendia.support.overdue_hours_high'),
            self::Normal => (int) config('atendia.support.overdue_hours'),
            self::Low => null,
        };
    }

    /**
     * @return array<string, string> value => label, for a select.
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])->all();
    }
}
