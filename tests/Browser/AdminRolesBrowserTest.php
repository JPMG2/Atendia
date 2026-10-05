<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Roles y permisos — the shots a human looks at
|--------------------------------------------------------------------------
| The matrix read as a shape: one block per area, so a job is understood at
| a glance instead of as forty checkboxes in a column.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);

    // A second role, so the list is a list and not one row.
    $design = Role::findOrCreate('diseno', 'web');
    $design->syncPermissions(['access-admin-panel', 'testimonials.moderate', 'moderation.view']);

    $person = User::factory()->create(['email_verified_at' => now()]);
    $person->assignRole('support');

    $admin = User::factory()->create(['name' => 'Juan', 'email_verified_at' => now()]);
    $admin->assignRole('admin');

    $this->actingAs($admin->refresh());
});

test('the roles screen holds at every width in both themes', function (string $label, int $width, int $height, bool $dark): void {
    $page = visit(route('admin.roles'))->resize($width, $height);

    if ($dark) {
        $page->click('@theme-toggle');
    }

    $page->assertNoJavaScriptErrors()
        ->assertSee('Roles y permisos')
        ->assertSee('No se edita')
        ->assertSee('Abre todo')
        ->screenshot(filename: 'admin-roles-'.$label.'-'.($dark ? 'dark' : 'light'));

    $overflow = $page->script('document.documentElement.scrollWidth - window.innerWidth');

    expect((int) $overflow)->toBeLessThanOrEqual(0);

    $cut = $page->script(
        'Array.from(document.querySelectorAll(".pay-table-wrap"))
            .map(w => w.scrollWidth - w.clientWidth)
            .reduce((a, b) => Math.max(a, b), 0)'
    );

    expect((int) $cut)->toBe(0);
})->with([
    'desktop light' => ['desktop', 1280, 900, false],
    'desktop dark' => ['desktop', 1280, 900, true],
    'phone light' => ['phone', 390, 844, false],
]);

test('the matrix is read by area, not as a list of keys', function (): void {
    $page = visit(route('admin.roles'))->resize(1280, 1200);

    $page->click('Nuevo rol')
        ->assertSee('Entrar al panel va incluido')
        ->assertSee('Soporte')
        ->assertSee('Atender los reportes de los negocios')
        ->assertSee('Catálogos')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'admin-roles-matrix');
});
