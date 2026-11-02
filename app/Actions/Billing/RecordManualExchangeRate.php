<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\ExchangeRateSource;
use App\Models\ExchangeRate;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * The last link of the chain: the rate she types when no source answers. It
 * charges as soon as its value date is in force (the jump question was
 * already asked on screen) and stays until a source brings a newer date.
 */
class RecordManualExchangeRate
{
    public function handle(User $by, CarbonImmutable $rateDate, float $official): ExchangeRate
    {
        return Cache::lock(FetchExchangeRate::WRITE_LOCK, 15)->block(10, function () use ($by, $rateDate, $official): ExchangeRate {
            $row = ExchangeRate::forDate($rateDate) ?? new ExchangeRate([
                'currency' => ExchangeRate::CURRENCY,
                'rate_date' => $rateDate,
            ]);

            $row->fill([
                'official' => $official,
                'source' => ExchangeRateSource::Manual,
                'confirmed_at' => now(),
                'entered_by' => $by->getKey(),
            ])->save();

            return $row;
        });
    }
}
