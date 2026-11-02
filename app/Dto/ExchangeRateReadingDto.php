<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enums\ExchangeRateSource;
use Carbon\CarbonImmutable;

/** What one source said: the official rate, its value date and, when it has one, the parallel. */
final readonly class ExchangeRateReadingDto
{
    public function __construct(
        public ExchangeRateSource $source,
        public CarbonImmutable $rateDate,
        public float $official,
        public ?float $parallel = null,
    ) {}
}
