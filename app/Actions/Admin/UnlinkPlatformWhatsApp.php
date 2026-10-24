<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Services\EvolutionApi;
use App\Services\PlatformWhatsApp;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

/**
 * Closes the phone's session on the platform's line. The instance stays: it is
 * linked again with a new QR, and deleting it is never offered from here.
 */
class UnlinkPlatformWhatsApp
{
    public function __construct(private readonly EvolutionApi $evolution) {}

    /**
     * @throws RuntimeException when no platform instance is configured
     * @throws ConnectionException
     */
    public function handle(): void
    {
        Gate::authorize('integrations.manage');

        $instance = PlatformWhatsApp::instance();

        if ($instance === '') {
            throw new RuntimeException('No platform WhatsApp instance is configured.');
        }

        $this->evolution->logout($instance);
    }
}
