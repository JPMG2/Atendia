<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\User;
use Database\Seeders\BusinessActivitySeeder;
use Database\Seeders\BusinessSectorSeeder;
use Database\Seeders\CatalogFormSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->setLocale('es');
});

/**
 * The activity form gained a fifth field on its second row. A row is declared,
 * not wrapped by luck: it has to end at the right edge on a laptop and stack
 * cleanly on a phone, with the switch readable in both.
 */
test('the activity form keeps the WhatsApp catalog switch inside the row', function (int $width): void {
    foreach ([RolesAndPermissionsSeeder::class, CatalogFormSeeder::class, BusinessSectorSeeder::class, BusinessActivitySeeder::class] as $seeder) {
        $this->seed($seeder);
    }

    $admin = User::factory()->create();
    $admin->syncRoles('admin');
    $this->actingAs($admin);

    $page = visit('/admin/catalogs')->resize($width, 900);
    $page->click('Actividades')->click('Crear actividad')->assertNoJavaScriptErrors();
    $page->wait(1)->assertSee('Catálogo de WhatsApp')->screenshot(filename: 'activity-catalog-switch-'.$width);

    // The switch sits inside the form card, never sticking out of the page.
    $overflow = (int) $page->script('document.documentElement.scrollWidth - window.innerWidth');

    expect($overflow)->toBeLessThanOrEqual(0);
})->with([1280, 390]);

test('a risky business name is warned about on the connect card and still connects', function (): void {
    config()->set('services.evolution.url', 'http://evolution.test');
    Http::fake(['http://evolution.test/*' => Http::response(['instance' => ['state' => 'close']])]);
    $this->seed(RolesAndPermissionsSeeder::class);

    $business = Business::factory()->create(['name' => 'PANADERIA 🍞 WHATSAPP']);
    $owner = User::factory()->create(['business_id' => $business->id, 'email_verified_at' => now()]);
    $owner->assignRole('client');
    $this->actingAs($owner);

    $page = visit('/whatsapp')->resize(1280, 900);
    $page->assertSee('El nombre de tu negocio puede ser rechazado por Meta')
        ->assertSee('Conectar mi WhatsApp')
        ->assertNoJavaScriptErrors();
    $page->wait(1)->screenshot(filename: 'meta-name-warning');

    // The alert component builds its class name in PHP, so Tailwind used to
    // purge every `.alert-*` and the box came out transparent: measure the paint.
    $background = (string) $page->script(
        'getComputedStyle(document.querySelector("[data-testid=meta-name-warning]")).backgroundColor'
    );

    expect($background)->not->toBe('rgba(0, 0, 0, 0)')->and($background)->not->toBe('transparent');
});
