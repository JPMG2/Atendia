<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\LoginDevice;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * The API twin of LogoutRevokedDevice: a token whose device row is gone dies
 * on its next call, and a token shown with another install's id is refused,
 * so a token lifted from a phone is not enough. Unlike the web, an empty
 * device list is no free pass: every app token is born with its device row.
 */
class RejectRevokedDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $deviceId = (string) $request->header('X-Device-Id');

        if (! $user instanceof User) {
            return $next($request);
        }

        if ($deviceId === '') {
            return response()->json(['message' => __('api.device_required'), 'code' => 'device_required'], 400);
        }

        $fingerprint = LoginDevice::fingerprintForApp($deviceId);
        $token = $user->currentAccessToken();
        $revoked = response()->json(['message' => __('api.device_revoked'), 'code' => 'device_revoked'], 401);

        // IssueAppToken names every token after its install's fingerprint.
        if ($token instanceof PersonalAccessToken && $token->name !== $fingerprint) {
            return $revoked;
        }

        if (! $user->loginDevices()->where('fingerprint', $fingerprint)->exists()) {
            if ($token instanceof PersonalAccessToken) {
                $token->delete();
            }

            return $revoked;
        }

        return $next($request);
    }
}
