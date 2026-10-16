<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Where a browser says what the policy blocked, or would have blocked. Anyone
 * can post here, so it only ever writes a short log line, and only once an
 * hour for each directive and address: one broken page must not fill the log.
 */
class CspReportController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $report = json_decode((string) $request->getContent(), true)['csp-report'] ?? null;

        if (is_array($report)) {
            $directive = mb_substr((string) ($report['effective-directive'] ?? $report['violated-directive'] ?? '?'), 0, 40);
            $blocked = mb_substr((string) ($report['blocked-uri'] ?? '?'), 0, 200);

            if (Cache::add('csp:'.md5($directive.'|'.$blocked), 1, now()->addHour())) {
                Log::warning('csp.violation', [
                    'directive' => $directive,
                    'blocked' => $blocked,
                    'page' => mb_substr((string) ($report['document-uri'] ?? '?'), 0, 200),
                    'disposition' => (string) ($report['disposition'] ?? '?'),
                ]);
            }
        }

        return response()->noContent();
    }
}
