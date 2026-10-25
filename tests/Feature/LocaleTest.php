<?php

declare(strict_types=1);

use Stevebauman\Location\Facades\Location;
use Stevebauman\Location\Position;

/**
 * The marketing layout's @vite needs the build manifest; it is stubbed so the
 * landing renders without compiling assets.
 */
beforeEach(function (): void {
    $this->withoutVite();
});

/**
 * A fake Position for the given country, ready to mock Location with.
 */
function fakePosition(string $countryCode): Position
{
    $position = new Position;
    $position->countryCode = $countryCode;

    return $position;
}

test('the neutral base has no voseo: it lives in the es_AR overrides', function (): void {
    // Accented words that end in a stress mark and are plain Spanish for everyone.
    $neutral = ['está', 'esté', 'aquí', 'allá', 'allí', 'así', 'sí', 'ahí', 'estás', 'además', 'después', 'atrás', 'demás', 'detrás',
        'inglés', 'portugués', 'francés', 'caché', 'verás', 'tendrás', 'sabrá', 'responderá', 'aparecerá', 'avisé', 'guardé',
        'envié', 'dejé', 'enséñale', 'enséñasela', 'más', 'país', 'también', 'través', 'mí', 'té', 'café', 'bebé', 'sofá',
        'qué', 'acá', 'sé', 'leí'];

    $voseo = [];

    foreach (glob(base_path('lang/es/*.php')) as $file) {
        $values = new RecursiveIteratorIterator(new RecursiveArrayIterator(require $file));

        foreach ($values as $value) {
            if (! is_string($value)) {
                continue;
            }

            preg_match_all('/\b[\p{L}]+(?:á|é|í|ás|és|ís)\b/u', $value, $found);

            foreach ($found[0] as $word) {
                if (! in_array(mb_strtolower($word), $neutral, true)) {
                    $voseo[] = basename($file).': '.$word;
                }
            }

            if (preg_match('/\b(?:vos|sos|tenés|querés|podés)\b/u', $value) === 1) {
                $voseo[] = basename($file).': '.$value;
            }
        }
    }

    expect($voseo)->toBe([]);
});

it('prioriza la elección guardada en sesión por sobre la geolocalización', function (): void {
    // Once someone has chosen, no lookup happens at all.
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

it('dice las menciones con voseo en es_AR y con tuteo en la base, sin perder lo que no se pisa', function (): void {
    app()->setLocale('es_AR');

    expect(__('client.mention.not_following.body', ['accounts' => 'X', 'brand' => 'B']))->toContain('Seguilas y tocá')
        ->and(__('client.mention.withdraw_message'))->toContain('volvés')
        ->and(__('bell.lines.mention_offered', ['achievement' => 'una meta']))->toContain('querés')
        ->and(__('mentions.followers.stale'))->toBe('Vencido: volvé a contar')
        ->and(__('mentions.title'))->toBe('Menciones');

    app()->setLocale('es');

    expect(__('client.mention.not_following.body', ['accounts' => 'X', 'brand' => 'B']))->toContain('Síguelas y toca')
        ->and(__('mentions.followers.stale'))->toBe('Vencido: vuelve a contar');
});

it('sirve neutro (es_VE) a una visita desde Venezuela', function (): void {
    Location::shouldReceive('get')->andReturn(fakePosition('VE'));

    $response = $this->withServerVariables(['REMOTE_ADDR' => '190.202.0.1'])->get('/');

    // es_VE has no overrides yet: it falls back to the neutral variant.
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

    // REMOTE_ADDR defaults to 127.0.0.1 in tests.
    $this->get('/')->assertOk();

    expect(app()->getLocale())->toBe('es');
});

it('toma el país de la cabecera de Cloudflare sin consultar a nadie', function (): void {
    // The lookup is the quota and the licence we are trying not to depend on.
    Location::shouldReceive('get')->never();

    $this->withHeaders(['CF-IPCountry' => 'AR'])->get('/')->assertOk()->assertSee('por vos');

    expect(app()->getLocale())->toBe('es_AR');
});

it('acepta la cabecera en minúsculas y con espacios', function (): void {
    Location::shouldReceive('get')->never();

    $this->withHeaders(['CF-IPCountry' => ' ve '])->get('/')->assertOk();

    expect(app()->getLocale())->toBe('es_VE');
});

it('ignora una cabecera sin país (XX, T1 o basura) y vuelve a la consulta por IP', function (string $header): void {
    Location::shouldReceive('get')->once()->andReturn(fakePosition('AR'));

    $this->withHeaders(['CF-IPCountry' => $header])
        ->withServerVariables(['REMOTE_ADDR' => '200.42.0.1'])
        ->get('/')->assertOk();

    expect(app()->getLocale())->toBe('es_AR');
})->with(['XX', 'T1', 'ARG', '1', '']);

it('el panel de marca del login habla en tuteo y en voseo para es_AR, sin texto escrito en la vista', function (): void {
    $this->withSession(['locale' => 'es'])->get('/login')
        ->assertOk()
        ->assertSee('Automatiza tu WhatsApp y no pierdas más clientes.')
        ->assertDontSee('Automatizá')
        ->assertSee(__('auth.aside.points.fast'));

    $this->withSession(['locale' => 'es_AR'])->get('/login')
        ->assertOk()
        ->assertSee('Automatizá tu WhatsApp y no pierdas más clientes.')
        ->assertSee(__('auth.aside.points.catalog'));
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

it('says cita in Venezuela and keeps turno everywhere else', function (): void {
    expect(__('agenda.book', [], 'es_VE'))->toBe('Reservar una cita')
        ->and(__('agenda.book', [], 'es'))->toBe('Reservar un turno')
        ->and(__('agenda.book', [], 'es_AR'))->toBe('Reservar un turno');
});

it('keeps turno for working-hour shifts even in Venezuela', function (): void {
    expect(__('client.business.hours.add_shift', [], 'es_VE'))->toBe('Agregar turno')
        ->and(__('client.business.appointments.enabled', [], 'es_VE'))->toBe('Agenda de citas');
});

it('leaves no turno in the Venezuelan strings except working-hour shifts', function (): void {
    $left = collect(glob(lang_path('es_VE/*.php')))
        ->flatMap(fn (string $file): array => Arr::dot(require $file))
        ->filter(fn ($text): bool => is_string($text) && preg_match('/\bturnos?\b/i', $text) === 1)
        ->keys()->all();

    expect($left)->toBe([]);
});
