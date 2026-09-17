<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The webhook route has no session or token: this shared-secret header is its
 * whole lock. An empty configured secret refuses everything — an unconfigured
 * endpoint must fail closed, not open.
 */
class VerifyEvolutionWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('services.evolution.webhook_secret');
        $given = (string) $request->header('X-Webhook-Secret', '');

        if ($secret === '' || ! hash_equals($secret, $given)) {
            abort(401);
        }

        return $next($request);
    }
}
