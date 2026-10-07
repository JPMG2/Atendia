<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Business\VerifyWhatsAppLink;
use App\Enums\WhatsAppLinkState;
use App\Models\Business;
use Illuminate\Console\Command;

/**
 * Walks every linked business and makes `whatsapp_connected_at` agree with
 * the bridge. The webhook stays the fast path; this is the net under it, so
 * a delivery lost to a restart costs one tick of staleness instead of
 * showing a green pill until somebody notices by hand.
 */
class ReconcileWhatsAppLinks extends Command
{
    protected $signature = 'atendia:whatsapp-reconcile';

    protected $description = 'Check every linked business against the bridge and correct its connection stamp';

    public function handle(VerifyWhatsAppLink $verify): int
    {
        $businesses = Business::query()->whereNotNull('whatsapp_instance')->get();

        foreach ($businesses as $business) {
            $was = $business->isConnected();
            $state = $verify->handle($business);

            // Only transitions are worth a line: a quiet tick over a healthy
            // fleet should print nothing to read.
            if ($state === WhatsAppLinkState::Unverified) {
                $this->warn("{$business->whatsapp_instance}: bridge did not answer");

                continue;
            }

            if ($was !== ($state === WhatsAppLinkState::Connected)) {
                $this->line("{$business->whatsapp_instance}: {$state->value}");
            }
        }

        return self::SUCCESS;
    }
}
