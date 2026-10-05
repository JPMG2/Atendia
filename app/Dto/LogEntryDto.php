<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * One log entry, split for the screen. `raw` is the entry VERBATIM — headline
 * plus stack trace — because its whole point is being copied and pasted into
 * a help conversation without losing a character.
 */
final readonly class LogEntryDto
{
    /**
     * @param  int  $occurrences  How many times this same headline repeated
     * @param  string  $firstAt  When the first of them was written
     */
    public function __construct(
        public string $timestamp,
        public string $environment,
        public string $level,
        public string $message,
        public string $raw,
        public int $occurrences = 1,
        public string $firstAt = '',
    ) {}

    /** The same entry, now known to have happened more than once. */
    public function repeated(int $occurrences, string $firstAt): self
    {
        return new self(
            $this->timestamp,
            $this->environment,
            $this->level,
            $this->message,
            $this->raw,
            $occurrences,
            $firstAt,
        );
    }

    /**
     * @return array{timestamp: string, environment: string, level: string, message: string, raw: string, occurrences: int, firstAt: string}
     */
    public function toArray(): array
    {
        return [
            'timestamp' => $this->timestamp,
            'environment' => $this->environment,
            'level' => $this->level,
            'message' => $this->message,
            'raw' => $this->raw,
            'occurrences' => $this->occurrences,
            'firstAt' => $this->firstAt,
        ];
    }
}
