<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * The platform's own staff hold the keys to every business, so their session
 * is shorter than the 12 hours a client gets: it dies after N idle minutes,
 * and "remember me" never brings it back.
 *
 * Only a person counts as activity. The admin screens poll on their own, so a
 * tab left open would keep the session alive forever if a poll counted.
 */
class ExpireIdleStaffSession
{
    private const string LAST_SEEN = 'staff_last_seen_at';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->can('access-admin-panel')) {
            return $next($request);
        }

        $limit = (int) config('atendia.security.staff_idle_minutes') * 60;
        $now = now()->getTimestamp();
        $lastSeen = (int) $request->session()->get(self::LAST_SEEN, 0);

        // A recaller cookie signs the user in silently once the session is
        // gone: for staff that would undo the whole point of expiring it.
        $revivedByRemember = Auth::guard('web')->viaRemember();
        $idleTooLong = $lastSeen > 0 && $now - $lastSeen > $limit;

        if ($limit > 0 && ($revivedByRemember || $idleTooLong)) {
            return $this->end($request);
        }

        if (! $this->isPassiveLivewirePoll($request)) {
            $request->session()->put(self::LAST_SEEN, $now);
        }

        return $next($request);
    }

    private function end(Request $request): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // A Livewire request cannot follow a redirect: its 419 is what the
        // front already turns into the login screen.
        if ($request->hasHeader('X-Livewire')) {
            return response('', 419);
        }

        return redirect()->route('login')->with('status', __('auth.staff_idle'));
    }

    /**
     * A Livewire request that changed nothing and called only `$refresh`: the
     * poll, not the person.
     */
    private function isPassiveLivewirePoll(Request $request): bool
    {
        if (! $request->hasHeader('X-Livewire')) {
            return false;
        }

        foreach ((array) $request->input('components', []) as $component) {
            $calls = (array) ($component['calls'] ?? []);

            if (! empty($component['updates'])) {
                return false;
            }

            foreach ($calls as $call) {
                if (($call['method'] ?? null) !== '$refresh') {
                    return false;
                }
            }
        }

        return true;
    }
}
