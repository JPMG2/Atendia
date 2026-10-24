<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Services\EvolutionApi;
use App\Services\PlatformWhatsApp;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

/**
 * Gives the platform's WhatsApp line a QR to pair with. The instance is created
 * the first time and never again; it has no webhook on purpose, because nothing
 * is expected to come IN through the platform's line.
 */
class StartPlatformWhatsAppLink
{
    public function __construct(private readonly EvolutionApi $evolution) {}

    /**
     * @return string|null The QR as a data URI, or null once the line is already linked.
     *
     * @throws RuntimeException when no platform instance is configured
     * @throws ConnectionException|RequestException
     */
    public function handle(): ?string
    {
        Gate::authorize('integrations.manage');

        $instance = PlatformWhatsApp::instance();

        if ($instance === '') {
            throw new RuntimeException('No platform WhatsApp instance is configured.');
        }

        if (! in_array($instance, $this->evolution->instanceNames(), true)) {
            $this->evolution->createInstance($instance);
        }

        return $this->evolution->qrCode($instance);
    }
}
