<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enums\PlatformSensorStatus;

/**
 * One look at one thing: what it says, what to show under its name, and the
 * figure for its tile. Built by a probe and consumed by the sweep.
 */
final readonly class SensorReadingDto
{
    public function __construct(
        public string $key,
        public string $group,
        public PlatformSensorStatus $status,
        public ?string $value = null,
        public ?string $detail = null,
    ) {}
}
