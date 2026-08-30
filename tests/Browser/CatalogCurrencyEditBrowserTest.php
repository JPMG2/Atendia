<?php

declare(strict_types=1);

use App\Models\Currency;
use App\Models\User;
use Database\Seeders\CatalogFormSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(CatalogFormSeeder::class);

    Currency::factory()->create(['code' => 'USD', 'name' => 'Dólar Estadounidense']);
});

// visibleCatalogView() lives in tests/Pest.php: every catalog master shares it.

test('clicking a table row opens the edit form with the record already loaded', function (): void {
    // The row text has to match exactly to be clickable, so assert against what was
    // actually persisted. The model no longer lowercases the name: it is a proper noun.
    $currency = Currency::factory()->create([
        'code' => 'ARS',
        'name' => 'Peso Argentino',
        'symbol' => '$',
        'decimal_places' => 2,
    ]);

    $admin = User::factory()->create();
    $admin->syncRoles('admin');
    $this->actingAs($admin);

    $page = visit('/admin/catalogs');

    $page->click('Monedas')
        ->assertSee($currency->name);

    // The row click must swap the view AND load the record server-side.
    $page->click($currency->name)
        ->assertSee('Editando')
        ->assertValue('code', $currency->code)
        ->assertValue('name', $currency->name)
        ->assertValue('symbol', $currency->symbol)
        ->assertValue('decimal_places', (string) $currency->decimal_places)
        ->assertNoJavaScriptErrors();
});

test('saving an edit takes the user back to the list showing the new value', function (): void {
    // The save empties the server-side form, so staying on it left the user facing
    // blank inputs under a header that still read "Editar ARS".
    $currency = Currency::factory()->create([
        'code' => 'ARS', 'name' => 'Peso Argentino', 'symbol' => '$', 'decimal_places' => 2,
    ]);

    $admin = User::factory()->create();
    $admin->syncRoles('admin');
    $this->actingAs($admin);

    $page = visit('/admin/catalogs');
    $page->click('Monedas')->click($currency->name);

    $page->fill('name', 'Peso rioplatense');
    $page->click('Guardar cambios');

    // assertSee retries until the round trip lands and Alpine repaints the table;
    // the display check below does not retry, so it has to come after.
    $page->assertSee('Peso rioplatense')
        ->assertNoJavaScriptErrors();

    expect(visibleCatalogView($page))->toBe('list');

    expect($currency->fresh()->name)->toBe('Peso rioplatense');
});

test('starting a new currency after opening one for edit shows empty inputs', function (): void {
    $currency = Currency::factory()->create(['code' => 'ARS', 'name' => 'Peso Argentino']);

    $admin = User::factory()->create();
    $admin->syncRoles('admin');
    $this->actingAs($admin);

    $page = visit('/admin/catalogs');
    $page->click('Monedas')->click($currency->name);

    // Leave without saving, then start a new one: nothing of the old record may survive.
    $page->click('Volver')->click('Crear moneda');

    $page->assertValue('code', '')
        ->assertValue('name', '')
        ->assertValue('symbol', '')
        ->assertSee('Nueva moneda')
        ->assertNoJavaScriptErrors();
});

test('clearing the decimals field shows a field error and keeps the form on screen', function (): void {
    // This used to throw a TypeError server-side (null into a typed int), Livewire
    // answered 419 and the editor disappeared: no message, nothing saved.
    $currency = Currency::factory()->create([
        'code' => 'ARS', 'name' => 'Peso Argentino', 'symbol' => '$', 'decimal_places' => 2,
    ]);

    $admin = User::factory()->create();
    $admin->syncRoles('admin');
    $this->actingAs($admin);

    $page = visit('/admin/catalogs');
    $page->click('Monedas')->click($currency->name);

    $page->fill('decimal_places', '');
    $page->click('Guardar cambios');

    expect(visibleCatalogView($page))->toBe('form');

    $page->assertVisible('#if-decimal_places-err')
        ->assertNoJavaScriptErrors();

    expect($currency->fresh()->decimal_places)->toBe(2);
});

test('the front-end validation now mirrors the server and stops the request', function (): void {
    // The wording is the discriminator: this exact sentence comes from form-guard.js.
    // Laravel would say "El campo decimales no puede ser mayor que 2." instead, so
    // seeing this proves the round trip never happened.
    $currency = Currency::factory()->create([
        'code' => 'ARS', 'name' => 'Peso Argentino', 'symbol' => '$', 'decimal_places' => 2,
    ]);

    $admin = User::factory()->create();
    $admin->syncRoles('admin');
    $this->actingAs($admin);

    $page = visit('/admin/catalogs');
    $page->click('Monedas')->click($currency->name);

    $page->fill('decimal_places', '5');
    $page->click('Guardar cambios');

    $page->assertVisible('#if-decimal_places-err');

    expect($page->text('#if-decimal_places-err'))->toBe('Debe ser menor o igual a 2.');
    expect($currency->fresh()->decimal_places)->toBe(2);

    // A name below the server's min:3 is caught client-side too — it had no rule at all.
    $page->fill('decimal_places', '2')->fill('name', 'AB');
    $page->click('Guardar cambios');

    expect($page->text('#if-name-err'))->toBe('Debe tener al menos 3 caracteres.');
    expect($currency->fresh()->name)->toBe('Peso Argentino');

    $page->assertNoJavaScriptErrors();
});

test('editing after having opened the create form still says Editando', function (): void {
    // Regression: `mode` was set AFTER awaiting the server, so between the click
    // and the response it still held the previous value and the form opened
    // announcing the wrong thing. It only looked fine on a freshly loaded page.
    $currency = Currency::factory()->create(['code' => 'ARS', 'name' => 'Peso Argentino']);

    $admin = User::factory()->create();
    $admin->syncRoles('admin');
    $this->actingAs($admin);

    $page = visit('/admin/catalogs');
    $page->click('Monedas');

    $badge = fn (): string => (string) $page->script('document.querySelector(".catalog-form-badge").textContent');

    $page->click('Crear moneda');
    expect($badge())->toBe('Nueva');

    $page->click('Volver')->click($currency->name);
    expect($badge())->toBe('Editando');

    $page->assertNoJavaScriptErrors();
});
