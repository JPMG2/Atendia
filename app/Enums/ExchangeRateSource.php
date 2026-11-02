<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a stored rate came from, in the order they are tried. The code branches
 * on it (which reader runs, whether a later automatic value may replace it), so
 * it stays an enum and not a catalog.
 */
enum ExchangeRateSource: string
{
    case BcvPage = 'bcv_page';
    case DolarApi = 'dolarapi';
    case Manual = 'manual';

    /** What the screens call it. */
    public function label(): string
    {
        return __('exchange_rate.sources.'.$this->value);
    }
}
