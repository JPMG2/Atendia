<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Business;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A tenant was just born. An event, unlike the company mail sent inline:
 * this fires N times (one per client) and more effects will hang off it
 * (welcome today, provisioning and metrics tomorrow) without touching the
 * form that creates the record.
 */
class BusinessCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Business $business,
    ) {}
}
