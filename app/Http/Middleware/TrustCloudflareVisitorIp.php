<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Behind Cloudflare, Traefik reports Cloudflare's own address: the login history said "Newark"
 * and a limit per IP was one limit for a whole data centre. CF-Connecting-IP carries the visitor
 * and is believed only when the address the trusted proxies report is inside Cloudflare's ranges.
 * Only X-Forwarded-For is rewritten: the connection's address stays Traefik's, which keeps the
 * proto and host headers (https links) trusted.
 */
class TrustCloudflareVisitorIp
{
    public function handle(Request $request, Closure $next): Response
    {
        $peer = $request->ip();
        $visitor = $request->headers->get('CF-Connecting-IP');

        if ($peer !== null
            && $visitor !== null
            && filter_var($visitor, FILTER_VALIDATE_IP) !== false
            && IpUtils::checkIp($peer, config('atendia.cloudflare.ranges'))) {
            $request->headers->set('X-Forwarded-For', $visitor);
        }

        return $next($request);
    }
}
