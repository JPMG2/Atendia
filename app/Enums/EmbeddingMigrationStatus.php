<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a change of embedding model stands. Code branches on it (which button
 * is offered, which model rules), so it is an enum and not a catalog.
 */
enum EmbeddingMigrationStatus: string
{
    /** The new vectors are being made next to the old ones; the assistant still searches with the old. */
    case Building = 'building';

    /** Everything is converted: it can be switched on. */
    case Ready = 'ready';

    /** The new model rules; the old vectors are kept until she discards them. */
    case Switched = 'switched';

    /** The old vectors were discarded: there is no way back. */
    case Finished = 'finished';

    case Cancelled = 'cancelled';

    case RolledBack = 'rolled_back';

    public function label(): string
    {
        return __('admin.ai.embeddings.status.'.$this->value);
    }

    /** One change at a time: until it is closed no other can start. */
    public function isOpen(): bool
    {
        return in_array($this, [self::Building, self::Ready, self::Switched], true);
    }

    /** Whether this change put its model in force and has not been undone. */
    public function rules(): bool
    {
        return in_array($this, [self::Switched, self::Finished], true);
    }

    /** The semantic tone of its tag. */
    public function tone(): string
    {
        return match ($this) {
            self::Building => 'is-warning',
            self::Ready, self::Switched => 'is-brand',
            self::Finished => 'is-neutral',
            self::Cancelled, self::RolledBack => 'is-neutral',
        };
    }
}
