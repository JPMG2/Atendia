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

test('creating a user is one form above the list', function (): void {
    $page = visit(route('admin.users'))->resize(1280, 900);

    $page->click('Nuevo usuario')
        ->assertSee('No se elige contraseña')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'admin-users-create');
});
