<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\DeviceAdded;
use App\Mail\NewDeviceLogin;
use App\Messaging\Channels\Email;
use App\Models\LoginDevice;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Cache;
use Stevebauman\Location\Facades\Location;
use Throwable;

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
        $location = $this->approximateLocation(request()->ip());

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
     * "City, Country" cached a day per IP, same guards as SetLocale: private
     * IPs never geolocate and a failing lookup must never block a login.
     */
    private function approximateLocation(?string $ip): ?string
    {
        if ($ip === null || $ip === '' || in_array($ip, ['127.0.0.1', '::1'], true)) {
            return null;
        }

        return Cache::remember("geo:place:{$ip}", now()->addHours(24), function () use ($ip): ?string {
            try {
                $position = Location::get($ip);

                if ($position === false) {
                    return null;
                }

                return implode(', ', array_filter([$position->cityName, $position->countryName])) ?: null;
            } catch (Throwable) {
                return null;
            }
        });
    }
}
