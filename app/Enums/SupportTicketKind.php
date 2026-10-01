<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What the person is bringing. Asked AFTER the description, never before: a
 * form that demands a category before letting someone write is the first
 * reason a ticket never gets sent.
 */
enum SupportTicketKind: string
{
    case Problem = 'problem';
    case Idea = 'idea';
    case Question = 'question';

    public function label(): string
    {
        return __('support.kinds.'.$this->value);
    }

    public function icon(): string
    {
        return match ($this) {
            self::Problem => 'alert-triangle',
            self::Idea => 'sparkles',
            self::Question => 'message-circle',
        };
    }
}
