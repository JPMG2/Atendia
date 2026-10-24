<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enums\PlatformWhatsAppState;
use Carbon\CarbonImmutable;

/**
 * The platform's WhatsApp line at one moment: what the bridge said and when
 * it was asked. `since` and `reason` are only filled when they are true.
 */
final readonly class PlatformWhatsAppDto
{
    public function __construct(
        public string $instance,
        public PlatformWhatsAppState $state,
        public ?string $number,
        public ?CarbonImmutable $since,
        public ?string $reason,
        public CarbonImmutable $checkedAt,
    ) {}
}
