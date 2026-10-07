<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\Enums\WhatsAppLinkState;
use App\Messaging\Channels\Panel;
use App\Messaging\Panel\WhatsAppDisconnected;
use App\Models\Business;
use App\Models\WhatsAppLinkEvent;
use App\Services\EvolutionApi;
use Throwable;

/**
 * Asks the bridge what is TRUE about a business's link and makes our column
 * agree. The webhook used to be the column's only writer, so a delivery that
 * never arrives froze the stamp and the panel kept showing green over a
 * number that answers nobody — what a bridge restart produces every time.
 */
class VerifyWhatsAppLink
{
    public function __construct(private readonly EvolutionApi $evolution) {}

    /**
     * A business with no instance was never linked: that is knowledge, not a
     * guess, so it needs no probe. When the bridge itself does not answer we
     * return Unverified and touch NOTHING — overwriting a real stamp with a
     * network hiccup would trade one lie for another.
     */
    public function handle(Business $business): WhatsAppLinkState
    {
        $instance = $business->whatsapp_instance;

        if ($instance === null) {
            return WhatsAppLinkState::Disconnected;
        }

        try {
            $state = $this->evolution->connectionState($instance);
        } catch (Throwable $failure) {
            report($failure);

            return WhatsAppLinkState::Unverified;
        }

        $business->markLinkVerified();

        // Only `open` is a number that answers. `connecting` is Baileys
        // holding an unpaired socket open — it reads as "almost there" and
        // stays that way forever, so it counts as down.
        return $state === 'open'
            ? $this->markConnected($business)
            : $this->markDisconnected($business);
    }

    private function markConnected(Business $business): WhatsAppLinkState
    {
        if (! $business->isConnected()) {
            $business->update(['whatsapp_connected_at' => now()]);

            WhatsAppLinkEvent::record($business, true, 'paired');
        }

        return WhatsAppLinkState::Connected;
    }

    /** The notice fires on the fall only: the guard makes a re-check idempotent. */
    private function markDisconnected(Business $business): WhatsAppLinkState
    {
        if ($business->isConnected()) {
            $business->update(['whatsapp_connected_at' => null]);

            WhatsAppLinkEvent::record($business, false, 'bridge_closed');

            (new Panel($business, [], WhatsAppDisconnected::class))->send();
        }

        return WhatsAppLinkState::Disconnected;
    }
}
