<?php

declare(strict_types=1);

use App\Models\Country;
use App\Models\Province;
use App\Models\Region;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SocialNetworkSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
    $this->seed(SocialNetworkSeeder::class);

    $admin = User::factory()->create();
    $admin->syncRoles('admin');

    $this->actingAs($admin);
});

test('the company screen opens on the company data tab with no JS errors', function (): void {
    $page = visit('/admin/company');

    $page->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs()
        ->assertSee('Razón social')
        ->assertSee('Identificación fiscal')
        // The contact tab exists but its panel stays hidden until it is picked.
        ->assertDontSee('Email de soporte');
});

test('picking the contact tab swaps the panel', function (): void {
    $page = visit('/admin/company');

    $page->click('Contactos y redes')
        ->assertSee('Email de soporte')
        ->assertSee('Redes sociales')
        // The data panel is the one that hides now.
        ->assertDontSee('Razón social');
});

test('a social network row is added from the row itself', function (): void {
    // The list is not a fixed set: adding and removing live on the row, so the
    // whole thing stays on one line and no button floats underneath.
    $page = visit('/admin/company');

    $page->click('Contactos y redes')
        // Only one row exists at first, so the selector is unambiguous.
        ->click('@social-add')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});

test('changing the country empties the province and region combobox on screen too', function (): void {
    // The server nulls province_id/region_id, but what the user reads is Alpine's
    // own `query`, not the :value prop. If the label survived the reset, the field
    // would show a province that is no longer in its list and no longer in the DTO.
    $argentina = Country::factory()->create(['code' => 'ARG', 'name' => 'Argentina']);
    $chile = Country::factory()->create(['code' => 'CHL', 'name' => 'Chile']);

    $mendoza = Province::factory()->create(['country_id' => $argentina->id, 'name' => 'Mendoza']);
    Province::factory()->create(['country_id' => $chile->id, 'name' => 'Valparaiso']);

    Region::factory()->create(['province_id' => $mendoza->id, 'name' => 'Cuyo']);

    $page = visit('/admin/company');

    // Each pick is a `.live` round-trip that re-renders the combobox below it, so
    // the next field cannot be filled until that has landed.
    $page->fill('#if-country_id', 'argentina')->click('Argentina')->wait(1);
    $page->fill('#if-province_id', 'mendoza')->click('Mendoza')->wait(1);
    $page->fill('#if-region_id', 'cuyo')->click('Cuyo')->wait(1);

    // The three are filled in before the reset, so the assertion below means
    // something.
    expect($page->script('document.querySelector("#if-province_id").value'))->toBe('Mendoza');
    expect($page->script('document.querySelector("#if-region_id").value'))->toBe('Cuyo');

    $page->fill('#if-country_id', 'chile')->click('Chile')->wait(1);

    expect($page->script('document.querySelector("#if-province_id").value'))->toBe('');
    expect($page->script('document.querySelector("#if-region_id").value'))->toBe('');
});

test('the clear button empties a picked combobox in one click', function (): void {
    // Before it, changing your mind meant selecting the whole label and deleting
    // it by hand before the search box was usable again.
    Country::factory()->create(['code' => 'ARG', 'name' => 'Argentina']);

    $page = visit('/admin/company');

    $page->fill('#if-country_id', 'argentina')->click('Argentina');

    expect($page->script('document.querySelector("#if-country_id").value'))->toBe('Argentina');

    // Scoped to this combobox: every field on the screen has a clear button.
    $page->click('#if-country_id ~ .combo-clear');

    expect($page->script('document.querySelector("#if-country_id").value'))->toBe('');

    // And the field is left ready to type, which is the whole point.
    expect($page->script('document.activeElement.id'))->toBe('if-country_id');
});

test('saving with the required fields empty shows the errors instead of costing a request', function (): void {
    // The front validation only exists if Alpine really resolved `companyForm`:
    // a registration that arrives late throws an expression error and the save
    // button quietly does nothing.
    $page = visit('/admin/company');

    $page->click('Guardar cambios')
        ->assertNoJavaScriptErrors()
        ->assertSee('Este campo es obligatorio.');
});
