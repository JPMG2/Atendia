<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Business;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The connection slice was saved — wizard step or contact card. It fires
 * outside tryAction, so a listener's failure never turns a good save into
 * an error toast. `$hadEmail` travels along because after the save the
 * model cannot tell a first address from a corrected one.
 */
class BusinessConnectionSaved
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Business $business,
        public bool $hadEmail,
    ) {}
}
