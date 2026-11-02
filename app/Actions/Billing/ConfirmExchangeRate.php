<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Models\ExchangeRate;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/** She looked at a jump bigger than she allows and says it is right: from now on it charges. */
class ConfirmExchangeRate
{
    public function handle(User $by, ExchangeRate $rate): ExchangeRate
    {
        return Cache::lock(FetchExchangeRate::WRITE_LOCK, 15)->block(10, function () use ($by, $rate): ExchangeRate {
            $rate->forceFill(['confirmed_at' => now(), 'entered_by' => $by->getKey()])->save();

            return $rate;
        });
    }
}
