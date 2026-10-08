<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a model is able to do, and therefore which tasks it may be assigned to
 * and in what unit it is priced. Code branches on it (the price fields, the
 * select of every task), which is why it is an enum and not a catalog.
 */
enum AiCapability: string
{
    case Text = 'text';
    case Vision = 'vision';
    case Transcription = 'transcription';
    case Embedding = 'embedding';

    public function label(): string
    {
        return __('admin.ai.capabilities.'.$this->value);
    }

    public function description(): string
    {
        return __('admin.ai.capabilities.'.$this->value.'_hint');
    }

    /** Whether a model with this capability can do the work that needs `$needed`. */
    public function serves(self $needed): bool
    {
        return $this === $needed || ($this === self::Vision && $needed === self::Text);
    }

    /** Transcription is billed by the audio minute; the rest by the million tokens. */
    public function billsPerMinute(): bool
    {
        return $this === self::Transcription;
    }

    /**
     * Text models split the input in cached and not, and bill the output apart.
     * An embedding model reads and returns a vector: it bills the input only.
     */
    public function billsOutput(): bool
    {
        return ! $this->billsPerMinute() && $this !== self::Embedding;
    }

    /**
     * @return array<string, string> value => label, for a select.
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])->all();
    }
}
