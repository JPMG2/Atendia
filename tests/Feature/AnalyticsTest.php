<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Google Analytics on the public landing
|--------------------------------------------------------------------------
| Built switched off: with no measurement id nothing is drawn, loaded or
| allowed. Turning it on adds the consent banner and Google's origins to the
| landing, and to nothing else.
*/

const MEASUREMENT_ID = 'G-TEST000000';

beforeEach(function (): void {
    app()->setLocale('es');
});

function policyFor($response): string
{
    return (string) ($response->headers->get('Content-Security-Policy') ?? $response->headers->get('Content-Security-Policy-Report-Only'));
}

test('with no measurement id the landing draws no banner, loads no script and opens no Google origin', function (): void {
    config()->set('atendia.analytics.measurement_id', null);

    $response = $this->get('/')->assertOk();

    $response->assertDontSee('data-consent', false)
        ->assertDontSee('analytics-consent', false)
        ->assertDontSee('Preferencias de cookies');

    expect(policyFor($response))->not->toContain('googletagmanager')->not->toContain('google-analytics');
});

test('with an id the landing gets the banner, the footer link and the Google origins', function (): void {
    config()->set('atendia.analytics.measurement_id', MEASUREMENT_ID);

    $response = $this->get('/')->assertOk();

    $response->assertSee('data-consent="'.MEASUREMENT_ID.'"', false)
        ->assertSee('data-consent-accept', false)
        ->assertSee('data-consent-reject', false)
        ->assertSee('data-consent-open', false)
        ->assertSee('Cookies para medir las visitas')
        ->assertSee('Preferencias de cookies');

    expect(policyFor($response))
        ->toContain('script-src')
        ->toContain('https://www.googletagmanager.com')
        ->toContain('https://*.google-analytics.com')
        ->toContain('https://*.analytics.google.com');
});

test('Google is opened for the landing alone, never for the public policy page or the login', function (string $path): void {
    config()->set('atendia.analytics.measurement_id', MEASUREMENT_ID);

    $response = $this->get($path)->assertOk();

    expect(policyFor($response))->not->toContain('googletagmanager')->not->toContain('google-analytics');
    $response->assertDontSee('data-consent', false);
})->with(['/privacidad', '/login']);

test('the privacy policy admits the web analytics only while it is on', function (): void {
    config()->set('atendia.analytics.measurement_id', null);

    $this->get('/privacidad')
        ->assertSee('No usamos analítica de terceros ni publicidad.')
        ->assertDontSee('Cookies de la página web');

    config()->set('atendia.analytics.measurement_id', MEASUREMENT_ID);

    $this->get('/privacidad')
        ->assertDontSee('No usamos analítica de terceros ni publicidad.')
        ->assertSee('Cookies de la página web')
        ->assertSee('Google Analytics')
        ->assertSee('la app no usa analítica de terceros');
});

test('the banner and the policy speak the voseo of Argentina', function (): void {
    app()->setLocale('es_AR');

    expect(__('landing.cookies.body'))->toContain('aceptás')->toContain('podés')
        ->and(__('privacy.cookies.body'))->toContain('aceptás')->toContain('Podés');
});
