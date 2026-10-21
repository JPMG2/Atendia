<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Account\RecordLoginDevice;
use App\Models\LoginDevice;
use App\Models\User;
use Illuminate\Auth\Events\Login;

/**
 * Watches every login: leaves an activity row (the account's own trail) and
 * mails a security alert when the browser was never seen before. The first
 * device is remembered silently — alerting the registration would only
 * scare. NOT queued, same reason as SendBusinessWelcome: the Email channel
 * captures the visitor's locale from the running request.
 */
class SendNewDeviceLoginAlert
{
    public function handle(Login $event): void
    {
        /** @var User $user */
        $user = $event->user;
        $agent = (string) request()->userAgent();

        app(RecordLoginDevice::class)->handle($user, LoginDevice::fingerprintFor($agent), $agent);
    }
}
