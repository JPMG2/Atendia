<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\IpUtils;

/*
|--------------------------------------------------------------------------
| The visitor's address behind Cloudflare
|--------------------------------------------------------------------------
| Traefik reports Cloudflare's edge as the client. CF-Connecting-IP has the
| visitor, and is believed only when the edge really is Cloudflare's.
*/

beforeEach(function (): void {
    Route::get('/_test/visitor', fn (Request $request): array => ['ip' => $request->ip(), 'secure' => $request->isSecure()]);
});

/** A request as Traefik hands it over: its own private address, and the forwarded chain it built. */
function throughTraefik(string $forwardedFor, array $headers = []): TestResponse
{
    return test()->withServerVariables(['REMOTE_ADDR' => '10.11.0.2'])
        ->withHeaders(['X-Forwarded-For' => $forwardedFor, 'X-Forwarded-Proto' => 'https', ...$headers])
        ->getJson('/_test/visitor');
}

test('a visitor behind a Cloudflare edge is the address Cloudflare reports', function (): void {
    throughTraefik('104.22.195.63', ['CF-Connecting-IP' => '190.97.240.57'])
        ->assertJson(['ip' => '190.97.240.57']);
});

test('the same holds for an IPv6 edge and an IPv6 visitor', function (): void {
    throughTraefik('2606:4700::6810:84e5', ['CF-Connecting-IP' => '2800:370::1'])
        ->assertJson(['ip' => '2800:370::1']);
});

test('a header that arrives from outside Cloudflare is not believed', function (): void {
    // What anyone who reaches the server without going through Cloudflare could send.
    throughTraefik('8.8.8.8', ['CF-Connecting-IP' => '1.2.3.4'])
        ->assertJson(['ip' => '8.8.8.8']);
});

test('an internal request cannot borrow an address by sending the header', function (): void {
    $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
        ->withHeaders(['CF-Connecting-IP' => '1.2.3.4'])
        ->getJson('/_test/visitor')
        ->assertJson(['ip' => '127.0.0.1']);
});

test('a header that is not an address is ignored', function (): void {
    throughTraefik('104.22.195.63', ['CF-Connecting-IP' => 'not-an-ip'])
        ->assertJson(['ip' => '104.22.195.63']);
});

test('without the header nothing changes', function (): void {
    throughTraefik('104.22.195.63')->assertJson(['ip' => '104.22.195.63']);
});

test('the connection stays https: only the visitor address is rewritten', function (): void {
    throughTraefik('104.22.195.63', ['CF-Connecting-IP' => '190.97.240.57'])
        ->assertJson(['ip' => '190.97.240.57', 'secure' => true]);
});

test('every published range is a real range', function (): void {
    foreach (config('atendia.cloudflare.ranges') as $range) {
        [$network] = explode('/', $range);

        expect(IpUtils::checkIp($network, $range))->toBeTrue("{$range} does not contain its own network address");
    }

    expect(config('atendia.cloudflare.ranges'))->toHaveCount(22);
});
