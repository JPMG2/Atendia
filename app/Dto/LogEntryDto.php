<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * One log entry, as the reader parses it. `raw` is the entry VERBATIM — headline
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
}
