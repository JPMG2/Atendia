<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Http\Middleware\ExpireIdleStaffToken;
use App\Http\Middleware\StaffTokenGate;
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

        // Staff get their own ability and an idle clock: that token opens `admin/*`, and nothing else does.
        if (! $user->isStaff()) {
            return $user->createToken($fingerprint)->plainTextToken;
        }

        $token = $user->createToken($fingerprint, [StaffTokenGate::ABILITY]);
        ExpireIdleStaffToken::begin($token->accessToken);

        return $token->plainTextToken;
    }
}
