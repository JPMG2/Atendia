<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Inicio del admin — "vencen esta semana" del doble factor
|--------------------------------------------------------------------------
| The notice exists to be acted on: it names who is about to be locked out
| and takes her to Usuarios already filtered. With nobody due it says
| nothing at all.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    config()->set('atendia.security.staff_two_factor', ['grace_days' => 7, 'since' => '2026-01-01']);
});

function homeAdmin(): User
{
    $admin = User::factory()->create(['name' => 'Juan', 'email_verified_at' => now(), 'two_factor_whatsapp_at' => now()]);
    $admin->assignRole('admin');

    return tap($admin->refresh(), fn (User $user) => test()->actingAs($user));
}

function supportCreated(string $name, string $when): User
{
    $user = User::factory()->create(['name' => $name, 'email_verified_at' => now()]);
    $user->assignRole('support');
    $user->forceFill(['created_at' => $when])->save();

    return $user->refresh();
}

test('it names who is due within the week and links to the users already filtered', function (): void {
    homeAdmin();
    supportCreated('Rocío Paz', now()->subDays(2)->toDateTimeString());

    Livewire::test('admin.home.index')
        ->assertSeeHtml('data-testid="two-factor-due"')
        ->assertSee('Rocío Paz')
        ->assertSeeHtml(e(route('admin.users', ['doble' => 'off'])));
});

test('it stays silent when nobody is due, past due or already protected', function (): void {
    homeAdmin();

    // Plazo ended three days ago: locked to the security page, not "this week".
    supportCreated('Vencida', now()->subDays(10)->toDateTimeString());

    $protected = supportCreated('Protegida', now()->subDays(2)->toDateTimeString());
    $protected->forceFill(['two_factor_whatsapp_at' => now()])->save();

    Livewire::test('admin.home.index')->assertDontSeeHtml('data-testid="two-factor-due"');
});

test('somebody who may not open Usuarios is not shown the notice', function (): void {
    supportCreated('Rocío Paz', now()->subDays(2)->toDateTimeString());

    $reader = User::factory()->create(['email_verified_at' => now(), 'two_factor_whatsapp_at' => now()]);
    $reader->assignRole('support');
    $reader->revokePermissionTo('users.view');
    $this->actingAs($reader->refresh());

    Livewire::test('admin.home.index')->assertDontSeeHtml('data-testid="two-factor-due"');
});
