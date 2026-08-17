<?php

declare(strict_types=1);

use App\Models\Country;
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

    $this->currency = Currency::factory()->create(['code' => 'ARS', 'name' => 'Peso Argentino']);

    $this->admin = User::factory()->create();
    $this->admin->syncRoles('admin');
});

test('clicking a table row opens the edit form with the record already loaded', function (): void {
    $country = Country::factory()->create([
        'code' => 'ARG',
        'name' => 'Argentina',
        'phone_code' => '54',
        'currency_id' => $this->currency->id,
    ]);

    $this->actingAs($this->admin);

    $page = visit('/admin/catalogs');

    $page->click('Países')
        ->assertSee($country->name);

    // The row click must swap the view AND load the record server-side.
    $page->click($country->name)
        ->assertSee('Editando')
        ->assertValue('code', $country->code)
        ->assertValue('name', $country->name)
        ->assertValue('phone_code', $country->phone_code)
        ->assertNoJavaScriptErrors();

    // The combobox shows the LABEL; the id travels in its hidden field.
    expect($page->script('document.querySelector("#if-currency_id").value'))
        ->toBe('ARS — Peso Argentino');
});

test('saving an edit takes the user back to the list showing the new value', function (): void {
    $country = Country::factory()->create([
        'code' => 'ARG', 'name' => 'Argentina', 'phone_code' => '54', 'currency_id' => $this->currency->id,
    ]);

    $this->actingAs($this->admin);

    $page = visit('/admin/catalogs');
    $page->click('Países')->click($country->name);

    $page->fill('name', 'República Argentina');
    $page->click('Guardar cambios');

    // assertSee retries until the round trip lands and Alpine repaints the table;
    // the display check below does not retry, so it has to come after.
    $page->assertSee('República Argentina')
        ->assertNoJavaScriptErrors();

    expect(visibleCatalogView($page))->toBe('list');

    expect($country->fresh()->name)->toBe('República Argentina');
});

test('picking a currency from the combobox really persists it', function (): void {
    // The hidden field posts the id as a STRING. Before the DTO cast this was a
    // TypeError under strict_types: Livewire answered 419 and the editor vanished.
    $country = Country::factory()->create([
        'code' => 'ARG', 'name' => 'Argentina', 'currency_id' => $this->currency->id,
    ]);
    $other = Currency::factory()->create(['code' => 'USD', 'name' => 'Dólar Estadounidense']);

    $this->actingAs($this->admin);

    $page = visit('/admin/catalogs');
    $page->click('Países')->click($country->name);

    // Type to filter, then pick the option: the whole point of the combobox.
    $page->fill('#if-currency_id', 'dolar');
    $page->click('USD — Dólar Estadounidense');
    $page->click('Guardar cambios');

    $page->assertSee('USD')
        ->assertNoJavaScriptErrors();

    expect($country->fresh()->currency_id)->toBe($other->id);
});

test('starting a new country after opening one for edit shows empty inputs', function (): void {
    $country = Country::factory()->create(['code' => 'ARG', 'name' => 'Argentina', 'currency_id' => $this->currency->id]);

    $this->actingAs($this->admin);

    $page = visit('/admin/catalogs');
    $page->click('Países')->click($country->name);

    // Leave without saving, then start a new one: nothing of the old record may survive.
    $page->click('Volver')->click('Crear país');

    $page->assertValue('code', '')
        ->assertValue('name', '')
        ->assertValue('phone_code', '')
        ->assertSee('Nuevo país')
        ->assertNoJavaScriptErrors();
});

test('the front-end validation stops the request before it reaches the server', function (): void {
    // The wording is the discriminator: this exact sentence comes from form-guard.js.
    $country = Country::factory()->create([
        'code' => 'ARG', 'name' => 'Argentina', 'currency_id' => $this->currency->id,
    ]);

    $this->actingAs($this->admin);

    $page = visit('/admin/catalogs');
    $page->click('Países')->click($country->name);

    $page->fill('name', 'AB');
    $page->click('Guardar cambios');

    $page->assertVisible('#if-name-err');

    expect($page->text('#if-name-err'))->toBe('Debe tener al menos 3 caracteres.');
    expect($country->fresh()->name)->toBe('Argentina');

    $page->assertNoJavaScriptErrors();
});

test('clearing the currency shows the error on the combobox, red border included', function (): void {
    // Emptying the search box is how the combobox deselects; assert that the Alpine
    // bag really reaches its aria-invalid hook (that is what paints it red).
    $country = Country::factory()->create(['code' => 'ARG', 'name' => 'Argentina', 'currency_id' => $this->currency->id]);

    $this->actingAs($this->admin);

    $page = visit('/admin/catalogs');
    $page->click('Países')->click($country->name);

    $page->fill('#if-currency_id', '');
    $page->click('Guardar cambios');

    expect(visibleCatalogView($page))->toBe('form');

    $page->assertVisible('#if-currency_id-err')
        ->assertNoJavaScriptErrors();

    expect($page->script('document.querySelector("#if-currency_id").getAttribute("aria-invalid")'))->toBe('true');
    expect($country->fresh()->currency_id)->toBe($this->currency->id);
});
