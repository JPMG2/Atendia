<?php

declare(strict_types=1);

use App\Models\Business;
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

    $admin = User::factory()->create();
    $admin->syncRoles('admin');
    $this->actingAs($admin);
});

test('deleting a held currency explains who holds it and keeps it', function (): void {
    $currency = Currency::factory()->create(['code' => 'USD', 'name' => 'Dólar de prueba']);
    Business::factory()->create(['currency_id' => $currency->id]);

    $page = visit('/admin/catalogs');

    $page->click('Monedas')
        ->click('Dólar de prueba')
        ->click('Eliminar')
        ->assertSee('No se puede eliminar')
        ->assertSee('1 negocio')
        ->assertSee('Desactivar')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'catalog-remove-blocked');

    expect(Currency::query()->find($currency->id))->not->toBeNull();

    // The way out the message names is one click: it switches the record off.
    $page->script('document.querySelector("[x-ref=accept]").click()');

    $page->wait(1)->assertSee('quedó inactivo')->assertNoJavaScriptErrors();

    expect($currency->fresh()->is_active)->toBeFalse();
});

test('deleting a free currency asks first and then removes it', function (): void {
    $currency = Currency::factory()->create(['code' => 'XXX', 'name' => 'Moneda sin uso']);

    $page = visit('/admin/catalogs');

    $page->click('Monedas')
        ->click('Moneda sin uso')
        ->click('Eliminar')
        ->assertSee('Esto no se puede deshacer')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'catalog-remove-confirm');

    $page->script('document.documentElement.classList.add("dark")');
    $page->screenshot(filename: 'catalog-remove-confirm-dark');

    // The dialog's title carries the same words as its button, so the button
    // is pressed by its ref, the way a person's Enter does.
    $page->script('document.documentElement.classList.remove("dark")');
    $page->script('document.querySelector("[x-ref=accept]").click()');

    $page->wait(1)
        ->assertSee('Moneda eliminada correctamente')
        ->assertNoJavaScriptErrors();

    expect(Currency::withTrashed()->find($currency->id))->toBeNull();
});
