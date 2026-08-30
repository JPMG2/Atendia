<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Stevebauman\Location\Facades\Location;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Resolves the visit's locale: the user's own choice in session first, then
 * geolocation by IP, then the default.
 *
 * Geolocation only SUGGESTS — once someone picks a variant in the selector it
 * sticks in session and no lookup happens again.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('locales.supported');
        $default = config('locales.default');

        $locale = $request->session()->get('locale');

        if (! in_array($locale, $supported, true)) {
            $locale = $this->detectFromIp($request, $supported, $default);
            $request->session()->put('locale', $locale);
        }

        App::setLocale($locale);

        return $next($request);
    }

    /**
     * Geolocaliza la IP y la mapea a un locale soportado.
     *
     * @param  array<int, string>  $supported
     */
    private function detectFromIp(Request $request, array $supported, string $default): string
    {
        $ip = $request->ip();

        // Private and local IPs never geolocate (development, internal requests):
        // no point calling the API for them.
        if ($ip === null || in_array($ip, ['127.0.0.1', '::1'], true)) {
            return $default;
        }

        $country = Cache::remember(
            "geo:country:{$ip}",
            now()->addHours(24),
            function () use ($ip): ?string {
                try {
                    $position = Location::get($ip);

                    return $position ? $position->countryCode : null;
                } catch (Throwable $e) {
                    return null;
                }
            }
        );

        $locale = config("locales.country_map.{$country}", $default);

        return in_array($locale, $supported, true) ? $locale : $default;
    }
}
