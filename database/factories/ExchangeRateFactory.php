<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ExchangeRateSource;
use App\Models\ExchangeRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ExchangeRate> */
class ExchangeRateFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'currency' => ExchangeRate::CURRENCY,
            'rate_date' => ExchangeRate::today(),
            'official' => 889.5754,
            'source' => ExchangeRateSource::BcvPage,
            'parallel' => 993.8328,
            'confirmed_at' => now(),
        ];
    }

    public function on(string $date): static
    {
        return $this->state(['rate_date' => $date]);
    }

    public function awaitingConfirmation(): static
    {
        return $this->state(['confirmed_at' => null]);
    }

    public function manual(): static
    {
        return $this->state(['source' => ExchangeRateSource::Manual, 'parallel' => null]);
    }
}
