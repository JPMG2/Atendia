<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\Models\Business;
use App\Services\EvolutionApi;

/**
 * Gets the business a live pairing QR. First call provisions its bridge
 * instance (named after the id — stable, unique, never user input) and
 * points the webhook home; later calls reuse it, so an interrupted linking
 * just resumes.
 */
class StartWhatsAppLink
{
    public function __construct(private readonly EvolutionApi $evolution) {}

    /** @return string|null The QR data URI, or null when already linked. */
    public function handle(Business $business): ?string
    {
        $instance = $business->whatsapp_instance;

        if ($instance === null) {
            $instance = 'business-'.$business->id;

            $this->evolution->createInstance($instance);
            $this->evolution->registerWebhook(
                $instance,
                (string) config('services.evolution.webhook_url'),
                ['MESSAGES_UPSERT', 'CONNECTION_UPDATE'],
                (string) config('services.evolution.webhook_secret'),
            );

            $business->update(['whatsapp_instance' => $instance]);
        }

        return $this->evolution->qrCode($instance);
    }
}
