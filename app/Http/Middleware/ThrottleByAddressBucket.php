<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Classes\Main\AddressBucket;
use Illuminate\Routing\Middleware\ThrottleRequests;
use RuntimeException;

/**
 * Laravel's `throttle:N,M` with the visitor counted by AddressBucket: the
 * framework keys an anonymous request on its exact IP, and one IPv6 line can
 * rotate through a whole /64, so sign-up, the demo chat (paid AI) and the
 * booking page reset their count with every new address. Named limiters key
 * themselves and are untouched.
 */
class ThrottleByAddressBucket extends ThrottleRequests
{
    protected function resolveRequestSignature($request)
    {
        if ($user = $request->user()) {
            return $this->hashed((string) $user->getAuthIdentifier());
        }

        if ($route = $request->route()) {
            return $this->hashed($route->getDomain().'|'.AddressBucket::of($request->ip()));
        }

        throw new RuntimeException('Unable to generate the request signature. Route unavailable.');
    }

    /** The parent's own key format, which it keeps private. */
    private function hashed(string $value): string
    {
        return self::$shouldHashKeys ? sha1($value) : $value;
    }
}
