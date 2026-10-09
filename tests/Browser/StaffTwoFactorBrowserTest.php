<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The second step of the team, in a real browser
|--------------------------------------------------------------------------
| The page where it is turned on, the notice that counts the days, and the
| owner's panel that takes somebody's away. Shot at the widths she uses.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);

    config()->set('atendia.security.staff_two_factor', ['grace_days' => 7, 'since' => '2026-01-01']);
});

/** A person of the team. */
function browserTeamMember(string $role, array $attributes = []): User
{
    $user = User::factory()->create(['email_verified_at' => now(), ...$attributes]);
    $user->syncRoles([$role]);

    return $user->refresh();
}

test('inside the plazo a notice counts the days on every screen, and the page that fixes it has no notice of its own', function (): void {
    $support = browserTeamMember('support', ['created_at' => now()->subDay()]);
    $this->actingAs($support);

    visit('/admin/soporte')->resize(1280, 900)
        ->assertNoJavaScriptErrors()
        ->assertSee('para activar el doble factor')
        ->screenshot(filename: 'staff-two-factor-banner');

    visit('/admin/seguridad')->resize(1280, 900)
        ->assertNoJavaScriptErrors()
        ->assertSee('Mi seguridad')
        ->assertSee('Tu WhatsApp')
        ->assertDontSee('Queda');
});

test('past the plazo every screen leads to the security page, which says why and what is left', function (string $label, int $width, bool $dark): void {
    $support = browserTeamMember('support', ['created_at' => now()->subDays(30), 'whatsapp' => null]);
    $this->actingAs($support);

    $page = visit('/admin/soporte')->resize($width, 900);

    if ($dark) {
        $page->click('@theme-toggle');
    }

    $page->assertNoJavaScriptErrors()
        ->assertPathIs('/admin/seguridad')
        ->assertSee('El plazo venció')
        ->assertSee('Tu WhatsApp')
        ->assertSee('Para activarlo, guarda arriba tu número')
        ->screenshot(filename: 'staff-two-factor-overdue-'.$label.($dark ? '-dark' : ''));

    expect((int) $page->script('document.documentElement.scrollWidth - window.innerWidth'))->toBeLessThanOrEqual(0);
})->with([
    'desktop' => ['desktop', 1280, false],
    'dark' => ['desktop', 1280, true],
    'phone' => ['phone', 390, false],
]);

test('the owner takes somebody\'s second step away from the users screen, with her password', function (): void {
    $admin = browserTeamMember('admin', ['name' => 'Dueña', 'password' => 'secret-password-1']);
    $target = browserTeamMember('support', ['name' => 'Rocío Paz', 'two_factor_whatsapp_at' => now()]);
    $this->actingAs($admin);

    visit('/admin/usuarios')->resize(1280, 900)
        ->assertNoJavaScriptErrors()
        ->assertSee('Doble factor: 1 de 2 personas del equipo')
        ->click('[data-testid="two-factor-reset-'.$target->id.'"]')
        ->assertSee('Restablecer el doble factor de Rocío Paz')
        ->screenshot(filename: 'staff-two-factor-reset-panel')
        ->fill('current_password', 'secret-password-1')
        ->click('Restablecer el doble factor')
        ->assertSee('Doble factor: 0 de 2 personas del equipo')
        ->assertSee('Plazo vencido')
        ->screenshot(filename: 'staff-two-factor-reset-done');

    expect($target->refresh()->two_factor_whatsapp_at)->toBeNull();
});
