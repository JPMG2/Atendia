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
    public function __construct(
        public string $timestamp,
        public string $environment,
        public string $level,
        public string $message,
        public string $raw,
    ) {}

    /**
     * @return array{timestamp: string, environment: string, level: string, message: string, raw: string}
     */
    public function toArray(): array
    {
        return [
            'timestamp' => $this->timestamp,
            'environment' => $this->environment,
            'level' => $this->level,
            'message' => $this->message,
            'raw' => $this->raw,
        ];
    }
}
