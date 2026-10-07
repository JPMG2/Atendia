<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What went wrong in a conversation, and how badly.
 *
 * The order is the whole point. A queue sorted by arrival is operationally
 * wrong — a customer the assistant never answered would wait behind six
 * threads a person is already handling. `weight` is what the screen sorts by,
 * never `created_at`.
 */
enum IncidentKind: string
{
    /** The assistant did not answer at all: the product failing in public. */
    case Unanswered = 'unanswered';

    /** A person was called in and nobody showed up. */
    case HandoffUnattended = 'handoff_unattended';

    /** The customer asked more than once and still got no answer. */
    case CustomerRepeated = 'customer_repeated';

    /** The business itself marked one of the assistant's answers as wrong. */
    case AnswerRejected = 'answer_rejected';

    /** The customer left the thread annoyed, by the assistant's own reading. */
    case CustomerUpset = 'customer_upset';

    /** Something broke behind the scenes: it has no business and no customer. */
    case JobFailed = 'job_failed';

    /**
     * Higher comes first. A rejected answer sits above an annoyed reading
     * because somebody who KNOWS the business said it was wrong, while the
     * reading is the assistant judging its own thread.
     */
    public function weight(): int
    {
        return match ($this) {
            self::Unanswered => 40,
            self::JobFailed => 30,
            // Insisting is what somebody does right before giving up, and it
            // is the only signal here the customer sends on purpose.
            self::CustomerRepeated => 25,
            self::HandoffUnattended => 20,
            self::AnswerRejected => 15,
            self::CustomerUpset => 10,
        };
    }

    /** Maps to the token palette: `danger` is for what is losing a customer now. */
    public function tone(): string
    {
        return match ($this) {
            self::Unanswered, self::JobFailed, self::CustomerRepeated => 'danger',
            self::HandoffUnattended, self::AnswerRejected => 'warning',
            self::CustomerUpset => 'info',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Unanswered => 'message-circle',
            self::CustomerRepeated => 'repeat',
            self::HandoffUnattended => 'users',
            self::AnswerRejected => 'thumbs-down',
            self::CustomerUpset => 'frown',
            self::JobFailed => 'zap',
        };
    }
}
