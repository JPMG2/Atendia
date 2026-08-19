<?php

declare(strict_types=1);

use App\Models\Country;
use App\Models\Currency;
use App\Models\CurrentStatus;
use App\Models\Province;
use App\Models\Region;
use App\Models\TaxCondition;
use App\Models\User;
use Database\Seeders\CatalogFormSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(CatalogFormSeeder::class);

    $currency = Currency::factory()->create(['code' => 'ARS', 'name' => 'Peso Argentino']);
    $this->country = Country::factory()->create(['code' => 'ARG', 'name' => 'Argentina', 'currency_id' => $currency->id]);
    $this->province = Province::factory()->create(['name' => 'Buenos Aires', 'country_id' => $this->country->id]);

    $this->admin = User::factory()->create();
    $this->admin->syncRoles('admin');
    $this->actingAs($this->admin);
});

test('clicking a province row opens the edit form with the record already loaded', function (): void {
    $page = visit('/admin/catalogs');

    $page->click('Provincias')->assertSee('Buenos Aires');

    $page->click('Buenos Aires')
        ->assertSee('Editando')
        ->assertValue('name', 'Buenos Aires')
        ->assertNoJavaScriptErrors();

    // The combobox shows the LABEL; the id travels in its hidden field.
    expect($page->script('document.querySelector("#if-country_id").value'))->toBe('ARG — Argentina');
});

test('saving a province takes the user back to the list showing the new value', function (): void {
    $page = visit('/admin/catalogs');
    $page->click('Provincias')->click('Buenos Aires');

    $page->fill('name', 'Gran Buenos Aires');
    $page->click('Guardar cambios');

    $page->assertSee('Gran Buenos Aires')->assertNoJavaScriptErrors();

    expect(visibleCatalogView($page))->toBe('list');
    expect($this->province->fresh()->name)->toBe('Gran Buenos Aires');
});

test('the region combobox disambiguates provinces by country', function (): void {
    Region::factory()->create(['name' => 'Zona Norte', 'province_id' => $this->province->id]);

    $page = visit('/admin/catalogs');
    $page->click('Regiones')->click('Zona Norte');

    $page->assertSee('Editando')->assertNoJavaScriptErrors();

    expect($page->script('document.querySelector("#if-province_id").value'))->toBe('Buenos Aires — ARG');
});

test('the state switch shows the word that matches the record, not the one it was created with', function (): void {
    // Regression: the word used to be read once by Alpine on init, so opening an
    // inactive record still said "Activa" while the knob was off.
    Region::factory()->create(['name' => 'Zona Norte', 'province_id' => $this->province->id, 'is_active' => false]);

    $page = visit('/admin/catalogs');
    $page->click('Regiones')->click('Zona Norte');

    $page->assertSee('Editando')->assertNoJavaScriptErrors();

    expect($page->script('document.querySelector("#if-is_active").checked'))->toBeFalse();
    expect(trim((string) $page->text('.field-switch .switch-word-off')))->toBe('Inactiva');
});

test('a tax condition shows both of its booleans with the right word', function (): void {
    TaxCondition::factory()->create([
        'code' => 'RI', 'name' => 'Responsable Inscripto',
        'country_id' => $this->country->id, 'discriminate_tax' => true, 'is_active' => true,
    ]);

    $page = visit('/admin/catalogs');
    $page->click('Condiciones fiscales')->click('Responsable Inscripto');

    $page->assertSee('Editando')
        ->assertValue('code', 'RI')
        ->assertNoJavaScriptErrors();

    // Both switches are on, so both read their "on" word.
    expect($page->script('document.querySelector("#if-discriminate_tax").checked'))->toBeTrue();
    expect($page->script('document.querySelector("#if-is_active").checked'))->toBeTrue();
});

test('the status master saves a new record from an empty form', function (): void {
    CurrentStatus::factory()->create(['name' => 'Activo']);

    $page = visit('/admin/catalogs');
    $page->click('Estados')->click('Crear estado');

    $page->fill('name', 'En proceso');
    // Por CSS y no por texto: "Crear estado" es a la vez el botón de alta del
    // toolbar y el de guardar del pie, y click() por texto no sabe cuál es cuál.
    $page->click('.catalog-form-foot .btn-primary');

    $page->assertSee('En proceso')->assertNoJavaScriptErrors();

    expect(CurrentStatus::where('name', 'En proceso')->exists())->toBeTrue();
});

test('the front-end validation stops the request before it reaches the server', function (): void {
    // The wording is the discriminator: this exact sentence comes from form-guard.js.
    $page = visit('/admin/catalogs');
    $page->click('Provincias')->click('Buenos Aires');

    $page->fill('name', 'AB');
    $page->click('Guardar cambios');

    $page->assertVisible('#if-name-err');

    expect($page->text('#if-name-err'))->toBe('Debe tener al menos 3 caracteres.');
    expect($this->province->fresh()->name)->toBe('Buenos Aires');

    $page->assertNoJavaScriptErrors();
});

test('the combobox dropdown is not clipped by the panel that holds the form', function (): void {
    // With `overflow:hidden` on .catalog-panel the option list was cut against the
    // bottom of the card — six countries rendered as two and a half. Measured, not
    // eyeballed: the list is allowed to extend past the panel, and its last option
    // has to be a real, clickable element.
    foreach (['VEN' => 'Venezuela', 'MEX' => 'México', 'ESP' => 'España', 'COL' => 'Colombia', 'CHL' => 'Chile'] as $code => $name) {
        Country::factory()->create(['code' => $code, 'name' => $name]);
    }

    $page = visit('/admin/catalogs');
    $page->click('Provincias')->click('Buenos Aires');
    $page->click('#if-country_id');

    $visible = (int) $page->script(<<<'JS'
        (() => {
            const list = document.querySelector('.combo-list');
            const panel = document.querySelector('.catalog-panel');
            const clipped = getComputedStyle(panel).overflow !== 'visible'
                && list.getBoundingClientRect().bottom > panel.getBoundingClientRect().bottom;

            return clipped ? 0 : list.querySelectorAll('.combo-option').length;
        })()
    JS);

    // Six countries: the five above plus the one the province already points at.
    expect($visible)->toBe(6);

    $page->assertNoJavaScriptErrors();
});

test('typing a country name filters the region list client-side', function (): void {
    // Two regions of a province called Córdoba, one in Argentina and one in Spain:
    // the country is the ONLY thing that tells them apart, so it is the search
    // that proves the column is useful and not decorative.
    $spain = Country::factory()->create(['code' => 'ESP', 'name' => 'España']);
    $cordobaArg = Province::factory()->create(['name' => 'Córdoba', 'country_id' => $this->country->id]);
    $cordobaEsp = Province::factory()->create(['name' => 'Córdoba', 'country_id' => $spain->id]);

    Region::factory()->create(['name' => 'Zona Sur', 'province_id' => $cordobaArg->id]);
    Region::factory()->create(['name' => 'Sierra Morena', 'province_id' => $cordobaEsp->id]);

    $page = visit('/admin/catalogs');
    $page->click('Regiones')->assertSee('Zona Sur')->assertSee('Sierra Morena');

    $page->fill('q', 'españa');

    $page->assertSee('Sierra Morena')
        ->assertDontSee('Zona Sur')
        ->assertNoJavaScriptErrors();
});

test('each status renders its tag with the colour saved for it', function (): void {
    // The colour is a token key, so the proof is the class the browser ends up
    // with — and that the stylesheet actually resolves it to a real colour rather
    // than leaving the tag transparent.
    CurrentStatus::factory()->create(['name' => 'Bloqueado', 'color' => 'danger']);

    $page = visit('/admin/catalogs');
    $page->click('Estados')->assertSee('Bloqueado');

    expect($page->script('document.querySelector(".status-tag").className'))
        ->toContain('is-danger');

    $background = $page->script('getComputedStyle(document.querySelector(".status-tag")).backgroundColor');

    expect($background)->not->toBe('rgba(0, 0, 0, 0)');

    $page->assertNoJavaScriptErrors();
});
