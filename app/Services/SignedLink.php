<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeInterface;
use Illuminate\Support\Facades\URL;

/**
 * Signed links for mails. The signature covers path and query only
 * (routes verify with `signed:relative`): mails are built in the queue
 * worker from APP_URL (http), while the reader arrives through the proxy
 * over https — an absolute signature broke on that scheme swap (403).
 */
class SignedLink
{
    /** @param  array<string, mixed>  $parameters */
    public static function temporary(string $route, DateTimeInterface $expiresAt, array $parameters = []): string
    {
        return url(URL::temporarySignedRoute($route, $expiresAt, $parameters, absolute: false));
    }
}
