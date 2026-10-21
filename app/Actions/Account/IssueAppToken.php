<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Models\LoginDevice;
use App\Models\User;

/**
 * The one door through which a mobile token is born: the install is recorded
 * (activity row, new-device alert) and gets a token named after its
 * fingerprint, so a revoked device can be told apart from the rest.
 */
class IssueAppToken
{
    public function __construct(private readonly RecordLoginDevice $recordDevice) {}

    public function handle(User $user, string $deviceId, string $deviceName): string
    {
        $fingerprint = LoginDevice::fingerprintForApp($deviceId);

        $this->recordDevice->handle($user, $fingerprint, 'Aliadito App · '.$deviceName);

        // One token per install: signing in again replaces it instead of piling up.
        $user->tokens()->where('name', $fingerprint)->delete();

        return $user->createToken($fingerprint)->plainTextToken;
    }
}
