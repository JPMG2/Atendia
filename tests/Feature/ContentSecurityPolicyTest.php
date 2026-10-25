<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| The content security policy
|--------------------------------------------------------------------------
| Where a page may load things from, and a mailbox for what the browser says it
| blocked. It starts in report-only so a forgotten origin shows up in the log
| and not as a broken screen.
*/

/** The policy a request got, in whichever header it came. */
function policyOf($response): ?string
{
    return $response->headers->get('Content-Security-Policy') ?? $response->headers->get('Content-Security-Policy-Report-Only');
}

test('pages are served in report-only by default and block nothing', function (): void {
    config()->set('atendia.csp.mode', 'report-only');

    $response = $this->get('/');

    expect($response->headers->get('Content-Security-Policy-Report-Only'))->not->toBeNull()
        ->and($response->headers->get('Content-Security-Policy'))->toBeNull();
});

test('in enforce mode the same policy blocks', function (): void {
    config()->set('atendia.csp.mode', 'enforce');

    $response = $this->get('/');

    expect($response->headers->get('Content-Security-Policy'))->not->toBeNull()
        ->and($response->headers->get('Content-Security-Policy-Report-Only'))->toBeNull();
});

test('the policy names what the pages really load and closes the rest', function (): void {
    $policy = policyOf($this->get('/'));

    expect($policy)
        ->toContain("default-src 'self'")
        ->toContain('https://fonts.googleapis.com')
        ->toContain('https://fonts.gstatic.com')
        ->toContain('https://tile.openstreetmap.org')
        ->toContain('https://pps.whatsapp.net')
        ->toContain("connect-src 'self' blob:")
        ->not->toContain('cloudflareinsights')
        ->toContain("object-src 'none'")
        ->toContain("base-uri 'self'")
        ->toContain("form-action 'self'")
        ->toContain("frame-ancestors 'self'")
        ->toContain('report-uri /csp-report');
});

test('the WebSocket origin follows the host and the scheme of the request', function (): void {
    expect(policyOf($this->get('https://aliadito.test/')))->toContain('connect-src \'self\' blob: wss://aliadito.test')
        ->and(policyOf($this->get('http://aliadito.test/')))->toContain('connect-src \'self\' blob: ws://aliadito.test');
});

test('a violation report is logged once an hour for each directive and address', function (): void {
    Cache::flush();
    Log::spy();
    $report = ['csp-report' => [
        'effective-directive' => 'script-src-elem',
        'blocked-uri' => 'https://evil.example/x.js',
        'document-uri' => 'https://aliadito.test/login',
        'disposition' => 'report',
    ]];

    $this->postJson('/csp-report', $report)->assertNoContent();
    $this->postJson('/csp-report', $report)->assertNoContent();
    $this->postJson('/csp-report', ['csp-report' => [...$report['csp-report'], 'blocked-uri' => 'https://other.example/y.js']])->assertNoContent();

    Log::shouldHaveReceived('warning')->twice();
    Log::shouldHaveReceived('warning')->with('csp.violation', Mockery::on(fn (array $context): bool => $context['directive'] === 'script-src-elem'
        && $context['blocked'] === 'https://evil.example/x.js'));
});

test('the report mailbox needs no session or token and ignores what it cannot read', function (): void {
    Log::spy();

    $this->call('POST', '/csp-report', [], [], [], ['CONTENT_TYPE' => 'application/csp-report'], 'not json')->assertNoContent();
    $this->call('POST', '/csp-report', [], [], [], ['CONTENT_TYPE' => 'application/csp-report'], '{"other":1}')->assertNoContent();

    Log::shouldNotHaveReceived('warning');
});

test('a long blocked address is cut before it reaches the log', function (): void {
    Cache::flush();
    Log::spy();

    $this->postJson('/csp-report', ['csp-report' => ['effective-directive' => 'img-src', 'blocked-uri' => str_repeat('a', 900)]])->assertNoContent();

    Log::shouldHaveReceived('warning')->with('csp.violation', Mockery::on(fn (array $context): bool => mb_strlen($context['blocked']) === 200));
});
