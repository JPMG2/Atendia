<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Classes\Main\StaffTwoFactor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Past the plazo, the admin panel opens on one page only: the one where the
 * second step is turned on. The lock is here, on the route, and not in hiding
 * the menu: a link nobody shows is still a link anybody can type.
 */
class RequireStaffTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $request->routeIs('admin.security')) {
            return $next($request);
        }

        if ((new StaffTwoFactor($user))->overdue) {
            return redirect()->route('admin.security')->with('status', __('security.staff.required'));
        }

        return $next($request);
    }
}
