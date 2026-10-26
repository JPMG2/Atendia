<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * The API twin of ExpireIdleStaffSession: a staff token dies after the web's
 * idle minutes, and only a person counts as activity (what the app does on its
 * own carries `X-Passive`). The clock lives in the cache; losing it kills the
 * token too, because failing open would cost the admin's keys.
 */
class ExpireIdleStaffToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user instanceof User ? $user->currentAccessToken() : null;

        if (! StaffTokenGate::holds($token)) {
            return $next($request);
        }

        // Someone who lost the staff key keeps no staff token: it dies instead of lingering half-valid.
        if (! $user->isStaff()) {
            $token->delete();
            Cache::forget(self::key($token));

            return response()->json(['message' => __('api.staff_revoked'), 'code' => 'staff_revoked'], 401);
        }

        $limit = (int) config('atendia.security.staff_idle_minutes') * 60;
        $now = now()->getTimestamp();
        $lastSeen = (int) Cache::get(self::key($token), 0);

        if ($limit > 0 && ($lastSeen === 0 || $now - $lastSeen > $limit)) {
            $token->delete();
            Cache::forget(self::key($token));

            return response()->json(['message' => __('api.staff_idle'), 'code' => 'staff_idle'], 401);
        }

        if (! $request->hasHeader('X-Passive')) {
            self::begin($token);
        }

        return $next($request);
    }

    /** Starts (or restarts) the idle clock of a staff token: at sign-in and on every call a person makes. */
    public static function begin(PersonalAccessToken $token): void
    {
        Cache::put(self::key($token), now()->getTimestamp(), now()->addDay());
    }

    private static function key(PersonalAccessToken $token): string
    {
        return 'staff-token-seen:'.$token->getKey();
    }
}
