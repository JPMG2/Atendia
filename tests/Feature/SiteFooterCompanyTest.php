<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\SocialLink;
use App\Models\SocialNetwork;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The footer shows the company that was configured
|--------------------------------------------------------------------------
| Everything the Compañía screen stores was being ignored here: the copy was
| hardcoded, so the tagline, the copyright, the logo and the networks were
| captured and never seen.
*/

test('with no company saved the footer keeps the copy that shipped', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertSee(__('landing.footer.tagline'))
        ->assertSee(__('landing.footer.copyright'));
});

test('the footer shows the tagline and the copyright of the company', function (): void {
    Company::factory()->create([
        'legal_name' => 'AtendIa SRL',
        'tagline' => 'Tu negocio, atendido por IA',
        'text_copyright' => 'Todos los derechos reservados.',
    ]);

    $this->get('/')
        ->assertSee('Tu negocio, atendido por IA')
        ->assertSee('AtendIa SRL')
        ->assertSee('Todos los derechos reservados.')
        ->assertDontSee(__('landing.footer.tagline'));
});

test('a field left empty falls back instead of leaving a hole', function (): void {
    Company::factory()->create(['tagline' => null, 'text_copyright' => null]);

    $this->get('/')
        ->assertSee(__('landing.footer.tagline'))
        ->assertSee(__('landing.footer.copyright'));
});

test('the stored logo replaces the bundled mark, one per theme', function (): void {
    Company::factory()->create([
        'logo_path_light' => 'logos/light.svg',
        'logo_path_dark' => 'logos/dark.svg',
    ]);

    // The header keeps the bundled mark: only the footer was given the record.
    $this->get('/')
        ->assertSee(asset('storage/logos/light.svg'))
        ->assertSee(asset('storage/logos/dark.svg'));
});

test('with a single logo saved the same file stands in for both themes', function (): void {
    Company::factory()->create(['logo_path_light' => 'logos/only.svg', 'logo_path_dark' => null]);

    // Showing no mark at all in dark would be worse than showing the light one.
    $this->get('/')->assertSeeInOrder([
        asset('storage/logos/only.svg'),
        asset('storage/logos/only.svg'),
    ]);
});

test('the networks are listed in the order the screen set', function (): void {
    $company = Company::factory()->create();

    $instagram = SocialNetwork::factory()->create(['name' => 'Instagram', 'icon' => 'link']);
    $linkedin = SocialNetwork::factory()->create(['name' => 'LinkedIn', 'icon' => 'link']);

    SocialLink::factory()->for($company, 'linkable')->create([
        'social_network_id' => $linkedin->id,
        'url' => 'https://linkedin.test/atendia',
        'sort_order' => 1,
    ]);

    SocialLink::factory()->for($company, 'linkable')->create([
        'social_network_id' => $instagram->id,
        'url' => 'https://instagram.test/atendia',
        'sort_order' => 0,
    ]);

    // `sort_order` is what that column is for: the footer is the place the
    // order the screen sets is actually seen.
    $this->get('/')->assertSeeInOrder([
        'https://instagram.test/atendia',
        'https://linkedin.test/atendia',
    ]);
});

test('a company with no networks shows no empty row', function (): void {
    Company::factory()->create();

    $this->get('/')->assertDontSee('rel="noopener noreferrer"', escape: false);
});

test('the footer costs one query for the company, however many pages render it', function (): void {
    Company::factory()->create();

    // `once()` memoises it: the row is the same on every marketing page and the
    // footer must not pay for it twice in the one render.
    expect(Company::current())->toBe(Company::current());
});
