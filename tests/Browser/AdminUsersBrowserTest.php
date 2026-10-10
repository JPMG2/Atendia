<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\LoginActivity;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Usuarios y accesos — the shots a human looks at
|--------------------------------------------------------------------------
| The platform's own people only. A business owner is seeded on purpose: she
| must NOT be on this screen, and a picture is the fastest way to see it.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);

    // A client, to prove by sight that she does not belong here.
    $client = User::factory()->create(['name' => 'Sergio Andrés', 'email' => 'sergio@laboratoriovida.test', 'email_verified_at' => now()]);
    $client->assignRole('client');
    $client->business()->associate(Business::factory()->create(['name' => 'Laboratorio Vida']))->save();

    $worked = User::factory()->create(['name' => 'Rocío Paz', 'email' => 'rocio@atendia.test', 'email_verified_at' => now()]);
    $worked->assignRole('support');

    LoginActivity::query()->forceCreate([
        'user_id' => $worked->id, 'ip' => '190.0.0.1', 'location' => 'Mérida, Venezuela',
        'user_agent' => 'probe', 'created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2),
    ]);

    $pending = User::factory()->create(['name' => 'Martín Suárez', 'email' => 'martin@atendia.test', 'email_verified_at' => null]);
    $pending->assignRole('support');

    $admin = User::factory()->create(['name' => 'Juan', 'email' => 'admin@atendia.test', 'email_verified_at' => now()]);
    $admin->assignRole('admin');

    $this->actingAs($admin->refresh());
});

test('the access screen holds at every width in both themes', function (string $label, int $width, int $height, bool $dark): void {
    $page = visit(route('admin.users'))->resize($width, $height);

    if ($dark) {
        $page->click('@theme-toggle');
    }

    $page->assertNoJavaScriptErrors()
        ->assertSee('Usuarios y accesos')
        ->assertSee('Rocío Paz')
        ->assertSee('Mérida, Venezuela')
        ->assertSee('Sin verificar')
        // The client belongs to Negocios; seeing her here is the bug this
        // screen was rebuilt to fix.
        ->assertDontSee('Sergio Andrés')
        ->screenshot(filename: 'admin-users-'.$label.'-'.($dark ? 'dark' : 'light'));

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

test('the history opens beside the list and holds at every width', function (string $label, int $width, int $height, bool $dark): void {
    $rocio = User::query()->where('email', 'rocio@atendia.test')->firstOrFail();

    activity()->causedBy($rocio)->performedOn(Business::factory()->create(['name' => 'Kiosco La Esquina con un nombre bastante largo para probar el corte']))->log('deleted');
    activity()->causedBy($rocio)->performedOn(Business::factory()->create(['name' => 'Panadería del Centro']))->log('updated');

    $page = visit(route('admin.users'))->resize($width, $height);

    if ($dark) {
        $page->click('@theme-toggle');
    }

    $page->click('@history-'.$rocio->id)
        ->assertSee('Historial de Rocío Paz')
        ->assertSee('Panadería del Centro')
        ->assertSee('Abrir en Auditoría')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'admin-users-history-'.$label.'-'.($dark ? 'dark' : 'light'));

    // The list stays where it was behind the panel, and the panel itself never scrolls sideways.
    $page->assertSee('Usuarios y accesos');
    expect((int) $page->script('document.querySelector(".slide-over-body").scrollWidth - document.querySelector(".slide-over-body").clientWidth'))->toBe(0);
    expect((int) $page->script('document.documentElement.scrollWidth - window.innerWidth'))->toBeLessThanOrEqual(0);
})->with([
    'desktop light' => ['desktop', 1280, 900, false],
    'desktop dark' => ['desktop', 1280, 900, true],
    'phone light' => ['phone', 390, 844, false],
]);

test('the question mark lists the keys of a table screen that has no keys of its own', function (): void {
    $page = visit(route('admin.users'))->resize(1280, 900);

    $page->script("document.body.dispatchEvent(new KeyboardEvent('keydown', {key: '?', bubbles: true}))");

    $page->assertSee('Teclas de esta pantalla')
        ->assertSee('recorren las filas')
        ->assertNoJavaScriptErrors();
});

test('the slash goes to the search and c clears the filters', function (string $route): void {
    $page = visit(route($route, ['buscar' => 'zzz-nadie']))->resize(1280, 900);

    $page->assertSee('Limpiar filtros');

    $page->script("document.body.dispatchEvent(new KeyboardEvent('keydown', {key: '/', bubbles: true}))");
    expect($page->script('document.activeElement.matches("[data-key-focus=\"/\"]")'))->toBeTrue();

    // The key line says it too, from the same markup the `?` dialog reads.
    $page->assertSee('va al buscador')->assertSee('limpia los filtros');

    $page->script("document.activeElement.blur(); document.body.dispatchEvent(new KeyboardEvent('keydown', {key: 'c', bubbles: true}))");
    $page->wait(1)->assertDontSee('Limpiar filtros')->assertNoJavaScriptErrors();

    // Businesses keeps an empty `buscar=` in the URL; either way, the term is gone.
    expect($page->script('new URL(location.href).searchParams.get("buscar") ?? ""'))->toBe('');
})->with(['users' => ['admin.users'], 'businesses' => ['admin.businesses']]);

test('creating a user is one form above the list', function (): void {
    $page = visit(route('admin.users'))->resize(1280, 900);

    $page->click('Nuevo usuario')
        ->assertSee('No se elige contraseña')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'admin-users-create');
});
