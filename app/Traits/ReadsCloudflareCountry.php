<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Http\Request;

/**
 * The country Cloudflare already worked out for the visit, shared by the web
 * and the API so both pick a regional variant from the same source.
 */
trait ReadsCloudflareCountry
{
    /**
     * Free and with no quota. Anyone forging it only changes their own language,
     * so it needs no trust check. XX (unknown) and T1 (Tor) carry no country.
     */
    private function countryFromCloudflare(Request $request): ?string
    {
        $code = strtoupper(trim((string) $request->header('CF-IPCountry')));

        return preg_match('/^[A-Z]{2}$/', $code) === 1 && $code !== 'XX' ? $code : null;
    }
}
