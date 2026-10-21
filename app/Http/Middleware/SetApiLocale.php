<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * The app has no session and no browser to guess a country from: its language
 * is the one the phone announces in `Accept-Language`, matched against the
 * same whitelist as the web. Anything unknown falls to the default.
 */
class SetApiLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('locales.supported');

        $locale = $request->getPreferredLanguage($supported);

        App::setLocale(in_array($locale, $supported, true) ? $locale : config('locales.default'));

        return $next($request);
    }
}
