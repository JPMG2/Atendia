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
 * Resuelve el locale de la visita con esta prioridad:
 *
 *   1. Elección explícita del usuario (sesión) — manda siempre.
 *   2. Geolocalización por IP (stevebauman/location), cacheada por IP.
 *   3. Locale por defecto (config('locales.default')).
 *
 * La geolocalización solo SUGIERE: en cuanto el usuario elige una variante
 * desde el selector, queda fija en sesión y no se vuelve a geolocalizar.
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

        // Las IPs privadas/locales no geolocalizan (entorno de desarrollo,
        // requests internos). Evitamos pegarle a la API en vano.
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
