<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tells the browser where this page may load things from. The origins live in
 * config('atendia.csp'); the one thing that depends on the request is the
 * WebSocket, which rides the same host as the page.
 */
class ContentSecurityPolicy
{
    public const string REPORT_PATH = '/csp-report';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $header = config('atendia.csp.mode') === 'enforce'
            ? 'Content-Security-Policy'
            : 'Content-Security-Policy-Report-Only';

        $response->headers->set($header, $this->policy($request));

        return $response;
    }

    private function policy(Request $request): string
    {
        /** @var array<string, list<string>> $directives */
        $directives = config('atendia.csp.directives');

        $directives['connect-src'][] = ($request->isSecure() ? 'wss://' : 'ws://').$request->getHttpHost();
        $directives['report-uri'] = [self::REPORT_PATH];

        return collect($directives)
            ->map(fn (array $sources, string $name): string => $name.' '.implode(' ', $sources))
            ->implode('; ');
    }
}
