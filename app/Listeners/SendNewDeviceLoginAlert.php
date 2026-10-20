<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\DeviceAdded;
use App\Mail\NewDeviceLogin;
use App\Messaging\Channels\Email;
use App\Models\LoginDevice;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Locale;

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
        $ip = (string) request()->ip();
        $agent = (string) request()->userAgent();
        $location = $this->approximateLocation();

        $user->loginActivities()->create([
            'ip' => $ip,
            'location' => $location,
            'user_agent' => $agent,
        ]);

        // The trail is for "was that me?", not forensics: 90 days is plenty
        // and keeps the table from growing forever.
        $user->loginActivities()->where('created_at', '<', now()->subDays(90))->delete();

        $fingerprint = LoginDevice::fingerprintFor($agent);
        $device = $user->loginDevices()->firstWhere('fingerprint', $fingerprint);

        if ($device !== null) {
            $device->update(['ip' => $ip, 'last_login_at' => now()]);

            return;
        }

        $isFirstDevice = $user->loginDevices()->doesntExist();

        $device = $user->loginDevices()->create([
            'fingerprint' => $fingerprint,
            'ip' => $ip,
            'location' => $location,
            'user_agent' => $agent,
            'last_login_at' => now(),
        ]);

        if (! $isFirstDevice) {
            (new Email($device, [$user->email], NewDeviceLogin::class))->send();
            DeviceAdded::dispatch($device);
        }
    }

    /**
     * "City, Country" from the headers Cloudflare adds at the edge, free and with no
     * quota. Forging them only changes the place shown for the forger's own login, and
     * the origin accepts traffic from Cloudflare alone. Missing headers mean no place:
     * a login must never fail over a label.
     */
    private function approximateLocation(): ?string
    {
        $city = trim(strip_tags(rawurldecode((string) request()->header('CF-IPCity'))));
        $code = strtoupper(trim((string) request()->header('CF-IPCountry')));

        $country = preg_match('/^[A-Z]{2}$/', $code) === 1 && ! in_array($code, ['XX', 'T1'], true)
            ? Locale::getDisplayRegion("-{$code}", app()->getLocale())
            : null;

        return implode(', ', array_filter([mb_substr($city, 0, 80), $country])) ?: null;
    }
}
