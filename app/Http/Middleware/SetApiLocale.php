<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Traits\ReadsCloudflareCountry;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * The app has no session and no browser to guess a country from: its language
 * is the one the phone announces in `Accept-Language`, matched against the
 * same whitelist as the web. A phone that only says neutral Spanish (es-US,
 * es-419) gets the variant of the visit's country, exactly as the web does,
 * so a Venezuelan owner reads "Citas" whatever region the phone is set to.
 */
class SetApiLocale
{
    use ReadsCloudflareCountry;

    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('locales.supported');
        $default = config('locales.default');

        $locale = $request->getPreferredLanguage($supported);
        $locale = in_array($locale, $supported, true) ? $locale : $default;

        $country = $locale === $default ? $this->countryFromCloudflare($request) : null;

        if ($country !== null) {
            $mapped = config("locales.country_map.{$country}", $default);

            $locale = in_array($mapped, $supported, true) ? $mapped : $default;
        }

        App::setLocale($locale);

        return $next($request);
    }
}
