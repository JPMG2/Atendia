<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Events\DeviceAdded;
use App\Mail\NewDeviceLogin;
use App\Messaging\Channels\Email;
use App\Models\LoginDevice;
use App\Models\User;
use Locale;

/**
 * Leaves the account's activity row and remembers the device, mailing a
 * security alert when it was never seen before. The first device is
 * remembered silently: alerting the registration would only scare. The web
 * (Login event) and the mobile API both come through here. NOT queued: the
 * Email channel captures the visitor's locale from the running request.
 */
class RecordLoginDevice
{
    public function handle(User $user, string $fingerprint, string $userAgent): LoginDevice
    {
        $ip = (string) request()->ip();
        $location = $this->approximateLocation();

        $user->loginActivities()->create([
            'ip' => $ip,
            'location' => $location,
            'user_agent' => $userAgent,
        ]);

        // The trail is for "was that me?", not forensics: 90 days is plenty
        // and keeps the table from growing forever.
        $user->loginActivities()->where('created_at', '<', now()->subDays(90))->delete();

        $device = $user->loginDevices()->firstWhere('fingerprint', $fingerprint);

        if ($device !== null) {
            $device->update(['ip' => $ip, 'last_login_at' => now()]);

            return $device;
        }

        $isFirstDevice = $user->loginDevices()->doesntExist();

        $device = $user->loginDevices()->create([
            'fingerprint' => $fingerprint,
            'ip' => $ip,
            'location' => $location,
            'user_agent' => $userAgent,
            'last_login_at' => now(),
        ]);

        if (! $isFirstDevice) {
            (new Email($device, [$user->email], NewDeviceLogin::class))->send();
            DeviceAdded::dispatch($device);
        }

        return $device;
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
