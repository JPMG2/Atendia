<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turns away a phone older than the admin's floor before any data goes out: a
 * store install cannot be fixed from here and would show keys it cannot draw.
 * No build sent = an old one; a floor of 0 serves all. The floor is Play
 * Console's number and EAS counts iOS apart, so only Android is judged by it.
 */
class RequireSupportedAppBuild
{
    public function handle(Request $request, Closure $next): Response
    {
        $minimum = (int) config('atendia.app.min_build');
        // Builds older than this header are all Android: a missing one counts as Android.
        $platform = strtolower((string) $request->header('X-App-Platform', 'android'));

        if ($minimum <= 0 || $platform !== 'android') {
            return $next($request);
        }

        $sent = (string) $request->header('X-App-Build');
        $build = ctype_digit($sent) ? (int) $sent : 0;

        if ($build >= $minimum) {
            return $next($request);
        }

        return response()->json([
            'message' => __('api.update.message'),
            'code' => 'app_update_required',
            'update' => [
                'title' => __('api.update.title', ['brand' => Company::brand()]),
                'action' => __('api.update.action'),
                'detail' => __('api.update.detail', ['build' => $build, 'min' => $minimum]),
            ],
        ], 426);
    }
}
