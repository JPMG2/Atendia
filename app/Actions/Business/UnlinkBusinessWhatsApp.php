<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\Models\Business;
use App\Models\WhatsAppLinkEvent;
use App\Services\EvolutionApi;
use Illuminate\Http\Client\ConnectionException;
use RuntimeException;
use Throwable;

/**
 * The owner unlinks the phone on purpose, for instance because the linked
 * number is a personal one. The instance stays, so a new QR links the right
 * number; the "your WhatsApp fell" notice is not sent, she did it herself.
 */
class UnlinkBusinessWhatsApp
{
    public function __construct(private readonly EvolutionApi $evolution) {}

    /**
     * @throws ConnectionException when the bridge does not answer: the line is marked linked again
     * @throws RuntimeException when the phone is still linked after the logout: the same
     */
    public function handle(Business $business): void
    {
        $instance = $business->whatsapp_instance;

        if ($instance === null) {
            return;
        }

        // Marked BEFORE the logout: the bridge's own "closed" webhook then finds it already
        // down and does not send the "your WhatsApp fell" notice for something she did.
        $was = $business->refresh()->whatsapp_connected_at;
        $business->update(['whatsapp_connected_at' => null]);
        WhatsAppLinkEvent::record($business, false, WhatsAppLinkEvent::OWNER_UNLINKED);

        try {
            $this->evolution->logout($instance);

            // The logout call does not report a failure: the bridge is asked again before saying it is done.
            throw_if($this->evolution->connectionState($instance) === 'open', RuntimeException::class, 'The bridge still reports the number as linked.');
        } catch (Throwable $failure) {
            $business->update(['whatsapp_connected_at' => $was]);
            WhatsAppLinkEvent::record($business, true, 'unlink_failed');

            throw $failure;
        }
    }
}
