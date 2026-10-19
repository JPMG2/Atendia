<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Exportar — the one menu, on the screen that owns the list
|--------------------------------------------------------------------------
| The page overflow is measured; a panel clipped inside its card is caught
| by looking at the shots (the menu rises from the bottom on a phone).
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
    Company::factory()->create(['brand_name' => 'Aliadito', 'tagline' => 'Tu negocio, atendido por IA.']);

    $owner = User::factory()->create(['email_verified_at' => now()]);
    $owner->business()->associate(Business::factory()->create(['name' => 'Laboratorio Vida']))->save();

    foreach (['Ana Pérez', 'Anabel Rojas', 'Luis Mora', 'Carmen Díaz'] as $i => $name) {
        Customer::factory()->create(['business_id' => $owner->business_id, 'profile_name' => $name, 'phone' => '0414555010'.$i, 'last_activity_at' => now()->subDays($i)]);
    }

    $this->actingAs($owner->refresh());
});

test('with a filter active the menu asks what to export, in both themes and at every width', function (string $label, int $width, int $height, bool $dark): void {
    $page = visit(route('customers', ['buscar' => 'ana']))->resize($width, $height);

    if ($dark) {
        $page->click('@theme-toggle');
    }

    $page->assertNoJavaScriptErrors()
        ->assertSee('Ana Pérez')
        ->click('.export-menu .btn-export')
        ->assertSee(__('reports.menu.scope_title'))
        ->assertSee(__('reports.menu.view'))
        ->assertSee(__('reports.menu.all'))
        ->assertSee(__('reports.menu.formats.pdf.title'))
        ->screenshot(filename: 'export-menu-'.$label.'-'.($dark ? 'dark' : 'light'));

    expect((int) $page->script('document.documentElement.scrollWidth - window.innerWidth'))->toBeLessThanOrEqual(0);
})->with([
    'desktop light' => ['desktop', 1280, 900, false],
    'desktop dark' => ['desktop', 1280, 900, true],
    'tablet light' => ['tablet', 900, 900, false],
    'phone light' => ['phone', 390, 844, false],
    'phone dark' => ['phone', 390, 844, true],
]);

test('without a filter the menu goes straight to the formats', function (): void {
    visit(route('customers'))->resize(1280, 900)
        ->assertNoJavaScriptErrors()
        ->click('.export-menu .btn-export')
        ->assertSee(__('reports.menu.formats.xlsx.title'))
        ->assertDontSee(__('reports.menu.scope_title'))
        ->screenshot(filename: 'export-menu-no-filter');
});

test('without a tax id the menu says so and links to where it is added, and stops once it is set', function (): void {
    $business = auth()->user()->business;
    $business->forceFill(['tax_id' => null])->save();

    visit(route('customers'))->resize(1280, 900)
        ->click('.export-menu .btn-export')
        ->assertSee(__('reports.menu.tax_id_missing'))
        ->assertSee(__('reports.menu.tax_id_add'))
        ->screenshot(filename: 'export-menu-tax-id-missing');

    $business->forceFill(['tax_id' => 'J-12345678-9'])->save();

    visit(route('customers'))->resize(1280, 900)
        ->click('.export-menu .btn-export')
        ->assertDontSee(__('reports.menu.tax_id_missing'));
});

test('choosing everything points the links at the whole list', function (): void {
    $page = visit(route('customers', ['buscar' => 'ana']))->resize(1280, 900)
        ->click('.export-menu .btn-export')
        ->click('.export-menu .export-option:nth-child(2)');

    $href = (string) $page->script('document.querySelector(".export-menu a[href*=\"xlsx\"]").getAttribute("href")');

    expect($href)->toContain('alcance=todo');
});
