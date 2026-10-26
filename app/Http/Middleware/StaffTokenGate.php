<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Classes\Main\StaffTwoFactor;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps staff tokens and customers' tokens apart: a customer's never opens
 * `admin/*`, and a staff one never reaches a customer's own act (closing an
 * account). Mode `only` demands a staff token; `never` refuses it.
 */
class StaffTokenGate
{
    public const string ABILITY = 'staff';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $mode = 'only'): Response
    {
        $user = $request->user();
        $isStaffToken = $user instanceof User && self::holds($user->currentAccessToken());

        if ($mode === 'only' && ! $isStaffToken) {
            return response()->json(['message' => __('api.staff_only'), 'code' => 'staff_only'], 403);
        }

        if ($mode === 'never' && $isStaffToken) {
            return response()->json(['message' => __('api.staff_refused'), 'code' => 'staff_refused'], 403);
        }

        // The web shuts the panel to staff past the plazo of the second step; the app must not be the side door.
        if ($mode === 'only' && (new StaffTwoFactor($user))->overdue) {
            return response()->json(['message' => __('security.staff.required'), 'code' => 'staff_second_step'], 403);
        }

        return $next($request);
    }

    /** Exact match: a customer's `*` token would pass `can('staff')`, so the list is read as it is. */
    public static function holds(mixed $token): bool
    {
        return $token instanceof PersonalAccessToken && in_array(self::ABILITY, (array) $token->abilities, true);
    }
}
