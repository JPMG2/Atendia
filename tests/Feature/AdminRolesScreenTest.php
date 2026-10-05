<?php

declare(strict_types=1);

use App\Classes\Main\Access;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Roles y permisos (admin)
|--------------------------------------------------------------------------
| Where a job is described as the doors it opens, so the panel can grow with
| the people it will need — claims, design, call centre — without a seeder.
|
| The lock that matters: `admin` passes every gate through `Gate::before`.
| No screen may load it, save it or delete it.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
});

function rolesAdmin(): User
{
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    return tap($admin->refresh(), fn (User $user) => test()->actingAs($user));
}

test('the screen opens for the admin and closes for someone without the permission', function (): void {
    rolesAdmin();
    $this->get(route('admin.roles'))->assertOk();

    Auth::logout();
    $support = User::factory()->create(['email_verified_at' => now()]);
    $support->assignRole('support');

    $this->actingAs($support->refresh())->get(route('admin.roles'))->assertForbidden();
});

test('a new role is born from the screen, with the panel key included', function (): void {
    rolesAdmin();

    Livewire::test('admin.roles.index')
        ->call('create')
        ->set('form.name', 'call-center')
        ->set('form.permissions', ['support.view', 'businesses.view'])
        ->call('save')
        ->assertHasNoErrors();

    $role = Role::findByName('call-center');

    expect($role->hasPermissionTo(Access::PANEL))->toBeTrue()
        ->and($role->hasPermissionTo('support.view'))->toBeTrue()
        // Least privilege: what was not ticked is not granted.
        ->and($role->hasPermissionTo('settings.manage'))->toBeFalse();
});

test('the protected role cannot be opened on the matrix', function (): void {
    rolesAdmin();
    $owner = Role::findByName('admin');

    Livewire::test('admin.roles.index')
        ->call('edit', $owner->id)
        // Nothing opened: a screen that could load it could save it.
        ->assertSet('editing', null);

    expect(Access::staffRole($owner->id))->toBeNull();
});

test('the protected role cannot be deleted either', function (): void {
    rolesAdmin();
    $owner = Role::findByName('admin');

    Livewire::test('admin.roles.index')->call('destroy', $owner->id);

    expect(Role::query()->whereKey($owner->id)->exists())->toBeTrue();
});

test('its own name cannot be taken by a new role', function (): void {
    rolesAdmin();

    Livewire::test('admin.roles.index')
        ->call('create')
        ->set('form.name', 'admin')
        ->call('save')
        ->assertHasErrors('name');
});

test('a permission that is not on the matrix is refused', function (): void {
    rolesAdmin();

    Livewire::test('admin.roles.index')
        ->call('create')
        ->set('form.name', 'colado')
        // The client panel's key is not this matrix's to hand out.
        ->set('form.permissions', ['access-client-app'])
        ->call('save')
        ->assertHasErrors('permissions.0');
});

test('editing a role replaces what it opens, it does not add to it', function (): void {
    rolesAdmin();
    $support = Role::findByName('support');

    Livewire::test('admin.roles.index')
        ->call('edit', $support->id)
        ->set('form.permissions', ['logs.view'])
        ->call('save')
        ->assertHasNoErrors();

    $support->refresh();

    expect($support->hasPermissionTo('logs.view'))->toBeTrue()
        ->and($support->hasPermissionTo('support.view'))->toBeFalse()
        // The panel key survives every save: without it the role is keys to
        // a building nobody may walk into.
        ->and($support->hasPermissionTo(Access::PANEL))->toBeTrue();
});

test('a role somebody holds is not deleted out from under them', function (): void {
    rolesAdmin();
    $support = Role::findByName('support');

    $person = User::factory()->create(['email_verified_at' => now()]);
    $person->assignRole('support');

    Livewire::test('admin.roles.index')->call('destroy', $support->id);

    expect(Role::query()->whereKey($support->id)->exists())->toBeTrue();
});

test('a role nobody holds can go', function (): void {
    rolesAdmin();
    $spare = Role::findOrCreate('temporal', 'web');
    $spare->givePermissionTo(Access::PANEL);

    Livewire::test('admin.roles.index')->call('destroy', $spare->id);

    expect(Role::query()->whereKey($spare->id)->exists())->toBeFalse();
});

test('the matrix groups the keys by the area they open', function (): void {
    $matrix = Access::matrix();

    expect($matrix)->toHaveKey('support')
        ->toHaveKey('catalog')
        // The panel key is not a choice, and the client panel is not here.
        ->and(collect($matrix)->flatten()->all())
        ->not->toContain(Access::PANEL)
        ->not->toContain('access-client-app');
});

test('the owner role is listed but marked as untouchable', function (): void {
    rolesAdmin();

    Livewire::test('admin.roles.index')
        ->assertSee('No se edita')
        ->assertSee('Abre todo');
});
