<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Database\Seeders\CatalogFormSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The Plans master, in a real browser
|--------------------------------------------------------------------------
| The list she opens to see what each plan sells and who is on it, and the
| form where a figure changes — with the warning that the change reaches them.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(CatalogFormSeeder::class);

    $admin = User::factory()->create();
    $admin->syncRoles('admin');
    $this->actingAs($admin);

    // Two real businesses on Premium, so the list has a number to show and the form a warning to give.
    $businesses = Business::factory()->count(2)->create();
    Subscription::query()->whereIn('business_id', $businesses->modelKeys())->update(['plan' => 'premium']);
});

test('the list names each plan, its price and who is on it, with no way to create one', function (): void {
    $page = visit('/admin/catalogs')->resize(1280, 800);

    $page->click('Planes')->wait(1)
        ->assertNoJavaScriptErrors()
        ->assertSee('Emprende')
        ->assertSee('US$ 79')
        ->assertSee('Más elegido')
        ->assertDontSee('Crear plan')
        ->screenshot(filename: 'catalog-plans-list');

    // Every row the same height: the code is a second line in all of them, not a wrap in the longest.
    $heights = json_decode((string) $page->script('JSON.stringify(Array.from(document.querySelectorAll(".catalog-table tbody tr")).filter(r => r.querySelector(".plan-code")).map(r => Math.round(r.getBoundingClientRect().height)))'), true);

    expect(array_unique($heights))->toHaveCount(1);

    // The row for Premium says the two businesses riding it.
    expect($page->script('Array.from(document.querySelectorAll(".catalog-table tbody tr")).find(r => r.innerText.includes("Premium")).innerText'))->toContain('2');
});

test('the form opens on a plan with all its figures, the warning, and no delete button', function (string $label, int $width, bool $dark): void {
    $page = visit('/admin/catalogs')->resize($width, 900);

    if ($dark) {
        $page->click('@theme-toggle');
    }

    $page->click('Planes')->wait(0.8);
    $page->script('Array.from(document.querySelectorAll(".catalog-table tbody tr")).find(r => r.innerText.includes("Premium")).click()');

    $page->wait(1)
        ->assertNoJavaScriptErrors()
        ->assertSee('Hay 2 negocios en este plan')
        ->assertSee('Precio mensual')
        ->assertSee('Conversaciones al mes')
        ->assertSee('Días de prueba gratis')
        ->assertDontSee('Eliminar')
        ->screenshot(filename: 'catalog-plans-form-'.$label);

    expect((int) $page->script('document.documentElement.scrollWidth - window.innerWidth'))->toBeLessThanOrEqual(0)
        ->and((int) $page->script('document.querySelector(".catalog-input, .catalog-form").scrollWidth - document.querySelector(".catalog-form").clientWidth'))->toBeLessThanOrEqual(0);
})->with([
    'desktop' => ['desktop', 1280, false],
    'dark' => ['dark', 1280, true],
    'phone' => ['phone', 390, false],
]);

test('saving a changed price from the form reaches the plan and goes back to the list', function (): void {
    $page = visit('/admin/catalogs')->resize(1280, 900);

    $page->click('Planes')->wait(0.8);
    $page->script('Array.from(document.querySelectorAll(".catalog-table tbody tr")).find(r => r.innerText.includes("Negocio")).click()');
    $page->wait(1)->fill('price', '85')->click('Guardar cambios')->wait(1.2);

    $page->assertSee('US$ 85');

    expect(SubscriptionPlan::query()->where('code', 'negocio')->value('price'))->toBe(85);
});
