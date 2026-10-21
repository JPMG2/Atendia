<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\AppAccess;
use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Re-asked on every call, not only at sign-in: a plan that lapses or a
 * business that gets suspended closes the app on the next request, the same
 * way the web stops serving it.
 */
class EnsureAppAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $access = $user instanceof User ? $user->appAccess() : AppAccess::Allowed;

        return $access->isAllowed() ? $next($request) : self::denial($access);
    }

    public static function denial(AppAccess $access): JsonResponse
    {
        return response()->json([
            'message' => __('api.access.'.$access->value),
            'code' => 'app_access_denied',
            'reason' => $access->value,
        ], 403);
    }
}
