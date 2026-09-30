<?php

declare(strict_types=1);

namespace App\Messaging;

use App\Enums\PanelNotificationType;
use Illuminate\Database\Eloquent\Model;

/**
 * What the panel channel demands of a message. Unlike the mail and WhatsApp
 * analogs it does NOT return text: a bell row is worded when someone opens the
 * panel, and that reader may speak a different variant of Spanish than whoever
 * triggered it. So a message declares the data, and the screen writes the line.
 */
abstract class PanelMessage
{
    public function __construct(public Model $model) {}

    abstract public function type(): PanelNotificationType;

    /**
     * What identifies the FACT, not the delivery: the same fact revives its own
     * row instead of stacking a second one every time a sweep runs.
     */
    abstract public function dedupeKey(): string;

    /**
     * The placeholders its line needs.
     *
     * @return array<string, string|int>
     */
    abstract public function payload(): array;

    /** The screen that resolves it; null when there is nowhere to go. */
    public function url(): ?string
    {
        return null;
    }

    /**
     * Whether repeating the fact brings the row back unread. False for the ones
     * a sweep re-reports unchanged, which would never stay read.
     */
    public function revives(): bool
    {
        return true;
    }
}
