<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The lifecycle Zendesk settled on, minus the ones we cannot honour yet. New
 * is where every ticket lands and never returns to; Waiting is ours asking
 * the person something back.
 */
enum SupportTicketStatus: string
{
    case New = 'new';
    case Open = 'open';
    case Waiting = 'waiting';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return __('support.statuses.'.$this->value);
    }

    /** The tone the badge takes; the same four the panel already speaks. */
    public function tone(): string
    {
        return match ($this) {
            self::New => 'warning',
            self::Open => 'info',
            self::Waiting => 'brand',
            self::Resolved, self::Closed => 'success',
        };
    }

    /** Still ours to answer: what the admin inbox counts as pending. */
    public function isOpen(): bool
    {
        return in_array($this, [self::New, self::Open, self::Waiting], true);
    }

    /** The ball is in OUR court: a person is waiting for us, not the other way round. */
    public function isOurs(): bool
    {
        return in_array($this, [self::New, self::Open], true);
    }

    /**
     * The statuses a queue view stands for, by its name in the URL. Null is "no
     * filter": every ticket, the settled ones included.
     *
     * @return list<string>|null
     */
    public static function inScope(string $scope): ?array
    {
        $cases = match ($scope) {
            'answer' => [self::New, self::Open],
            'waiting' => [self::Waiting],
            'resolved' => [self::Resolved, self::Closed],
            'all' => null,
            default => [self::New, self::Open, self::Waiting],
        };

        return $cases === null ? null : array_map(fn (self $case): string => $case->value, $cases);
    }
}
