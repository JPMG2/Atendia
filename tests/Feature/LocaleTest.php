<?php

declare(strict_types=1);

use Stevebauman\Location\Facades\Location;
use Stevebauman\Location\Position;

/**
 * El @vite del layout de marketing necesita el manifest del build; lo
 * stubeamos para poder renderizar la landing sin compilar assets.
 */
beforeEach(function (): void {
    $this->withoutVite();
});

/**
 * Devuelve una Position falsa para el país dado, lista para mockear Location.
 */
function fakePosition(string $countryCode): Position
{
    $position = new Position;
    $position->countryCode = $countryCode;

    return $position;
}

it('prioriza la elección guardada en sesión por sobre la geolocalización', function (): void {
    // Si el usuario ya eligió, ni siquiera geolocalizamos.
    Location::shouldReceive('get')->never();

    $this->withSession(['locale' => 'es_VE'])->get('/')->assertOk();

    expect(app()->getLocale())->toBe('es_VE');
});

it('sirve voseo (es_AR) a una visita desde Argentina', function (): void {
    Location::shouldReceive('get')->andReturn(fakePosition('AR'));

    $response = $this->withServerVariables(['REMOTE_ADDR' => '200.42.0.1'])->get('/');

    $response->assertOk()->assertSee('por vos');
    expect(app()->getLocale())->toBe('es_AR');
});

it('sirve neutro (es_VE) a una visita desde Venezuela', function (): void {
    Location::shouldReceive('get')->andReturn(fakePosition('VE'));

    $response = $this->withServerVariables(['REMOTE_ADDR' => '190.202.0.1'])->get('/');

    // es_VE no tiene overrides todavía: cae al neutro en tuteo.
    $response->assertOk()->assertSee('por ti');
    expect(app()->getLocale())->toBe('es_VE');
});

it('cae al neutro (es) para un país sin mapeo', function (): void {
    Location::shouldReceive('get')->andReturn(fakePosition('CO'));

    $response = $this->withServerVariables(['REMOTE_ADDR' => '181.49.0.1'])->get('/');

    $response->assertOk()->assertSee('por ti');
    expect(app()->getLocale())->toBe('es');
});

it('cae al neutro cuando la IP es local y no geolocaliza', function (): void {
    Location::shouldReceive('get')->never();

    // REMOTE_ADDR por defecto en tests es 127.0.0.1.
    $this->get('/')->assertOk();

    expect(app()->getLocale())->toBe('es');
});

it('el selector guarda un locale válido en sesión y redirige', function (): void {
    $this->from('/')
        ->get('/idioma/es_AR')
        ->assertRedirect('/');

    expect(session('locale'))->toBe('es_AR');
});

it('el selector ignora un locale que no está en la lista blanca', function (): void {
    $this->from('/')->get('/idioma/xx')->assertRedirect('/');

    expect(session('locale'))->not->toBe('xx');
});
