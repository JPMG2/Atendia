<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hands the logged-in user's business to Postgres before the request runs,
 * so the RLS policies fence even raw SQL. The Eloquent scope reads the
 * tenant lazily and needs no middleware; the database does not.
 */
class SetTenantDatabaseContext
{
    public function handle(Request $request, Closure $next): Response
    {
        app(Tenant::class)->syncDatabase();

        return $next($request);
    }
}
