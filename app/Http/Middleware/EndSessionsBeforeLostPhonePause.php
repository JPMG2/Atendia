<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Business;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * While a business is paused for a lost phone, only the sessions that know
 * about the pause live on: the one that paused, and logins made after it.
 * Anything older dies on its next request, whatever its browser — a stolen
 * phone of the same model shares the user agent the devices list keys on.
 */
class EndSessionsBeforeLostPhonePause
{
    private const string KEY = 'lost_phone_pause_seen';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $pausedAt = $user instanceof User ? $user->business?->lost_phone_paused_at : null;

        if ($pausedAt !== null && $request->session()->get(self::KEY) !== $pausedAt->getTimestamp()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        return $next($request);
    }

    /** Lets the current session through the business's pause: the one that pauses, or a fresh login. */
    public static function admit(?Business $business): void
    {
        $pausedAt = $business?->lost_phone_paused_at;

        if ($pausedAt !== null && request()->hasSession()) {
            request()->session()->put(self::KEY, $pausedAt->getTimestamp());
        }
    }
}
