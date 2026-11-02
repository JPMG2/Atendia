<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Billing\FetchExchangeRate;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** The hourly look at the bolivar rate; the schedule keeps it inside her window. */
#[Signature('atendia:exchange-rate')]
#[Description('Reads the BCV rate (page, then dolarapi) and stores it with the parallel beside it')]
class FetchExchangeRateCommand extends Command
{
    public function handle(FetchExchangeRate $fetch): int
    {
        $check = $fetch->handle();

        $this->info('BCV page: '.($check['bcv'] ?? 'OK').' · dolarapi: '.($check['dolarapi'] ?? 'OK').' · stored: '.($check['stored'] ?? 'nothing'));

        return self::SUCCESS;
    }
}
