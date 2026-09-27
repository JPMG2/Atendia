<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enums\ModerationSeverity;

/** What moderation said about one file or text: the worst category and its score. */
final readonly class ModerationVerdictDto
{
    public function __construct(
        public ModerationSeverity $severity,
        public string $category = '',
        public float $score = 0.0,
    ) {}

    public static function clean(): self
    {
        return new self(ModerationSeverity::Clean);
    }

    public static function unavailable(): self
    {
        return new self(ModerationSeverity::Unavailable);
    }
}
