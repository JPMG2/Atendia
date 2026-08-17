<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Guard test: the browser reaches Reverb through the site's own origin.
|--------------------------------------------------------------------------
|
| The site is served over https by Traefik, and pusher-js forces `wss` on any
| https page no matter what `forceTLS` says. Pointing the client straight at
| the plain-ws port (IP:8080) therefore fails the handshake every single time.
|
| The fix is structural: nginx proxies /app/ and /apps/ to Reverb inside the
| container, and the client derives host and port from the page. These
| assertions fail loudly if either half is ever undone.
|
*/

test('nginx proxies the reverb websocket handshake', function (): void {
    $conf = file_get_contents(base_path('docker/nginx/conf.d/laravel.conf'));

    expect($conf)
        ->toContain('upstream reverb')
        // `^~` keeps the static-asset regex below from stealing these paths.
        ->toContain('location ^~ /app/')
        ->toContain('location ^~ /apps/')
        // Without the upgrade headers nginx downgrades the handshake to a plain
        // GET and Reverb answers 500 instead of 101 Switching Protocols.
        ->toContain('proxy_set_header Upgrade $http_upgrade')
        ->toContain('proxy_set_header Connection $connection_upgrade');
});

test('the websocket port survives long idle periods between pings', function (): void {
    $conf = file_get_contents(base_path('docker/nginx/conf.d/laravel.conf'));

    // nginx defaults to 60s, which silently kills idle sockets and puts the
    // browser into a reconnect loop.
    expect($conf)->toContain('proxy_read_timeout 3600s');
});

test('the echo client never hardcodes a host or a port', function (): void {
    $echo = file_get_contents(base_path('resources/js/echo.js'));

    expect($echo)
        ->toMatch('/wsHost:.*window\.location\.hostname/')
        // A literal IP or the raw reverb port is exactly the bug this guards.
        ->not->toMatch('/\b\d{1,3}(\.\d{1,3}){3}\b/')
        ->not->toContain(':8080');
});

test('the echo client authenticates private channels', function (): void {
    $echo = file_get_contents(base_path('resources/js/echo.js'));

    // MessageSent broadcasts on a PrivateChannel, so pusher-js has to POST to
    // /broadcasting/auth with the CSRF token or Laravel answers 419.
    expect($echo)
        ->toContain('/broadcasting/auth')
        ->toContain('X-CSRF-TOKEN');
});

test('the broadcasting auth endpoint is registered', function (): void {
    expect(app('router')->getRoutes()->getByName('broadcasting.auth') !== null
        || collect(app('router')->getRoutes())->contains(
            fn ($route): bool => $route->uri() === 'broadcasting/auth'
        ))->toBeTrue();
});
