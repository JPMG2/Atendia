<?php

declare(strict_types=1);

use App\Classes\Main\Access;
use App\Dto\RoleMenuNodeDto;
use App\Models\User;
use Database\Seeders\MenuSeeder;
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

/**
 * Every permission the editor paints, screens and actions, walking the tree.
 *
 * @param  list<RoleMenuNodeDto>  $nodes
 * @return list<string>
 */
function editorPermissions(array $nodes, bool $actionsOnly = false): array
{
    $found = [];

    foreach ($nodes as $node) {
        if (! $actionsOnly && $node->permission !== null) {
            $found[] = $node->permission;
        }

        array_push($found, ...$node->actions, ...editorPermissions($node->children, $actionsOnly));
    }

    return $found;
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

    Livewire::test('admin.roles.edit')
        ->set('form.name', 'call-center')
        ->set('form.permissions', ['support.view', 'businesses.view'])
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('role-saved');

    $role = Role::findByName('call-center');

    expect($role->hasPermissionTo(Access::PANEL))->toBeTrue()
        ->and($role->hasPermissionTo('support.view'))->toBeTrue()
        // Least privilege: what was not ticked is not granted.
        ->and($role->hasPermissionTo('settings.manage'))->toBeFalse();
});

test('the protected role cannot be opened on the matrix', function (): void {
    rolesAdmin();
    $owner = Role::findByName('admin');

    // Nothing opens: a screen that could load it could save it.
    $this->get(route('admin.roles.edit', $owner->id))->assertNotFound();

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

    Livewire::test('admin.roles.edit')
        ->set('form.name', 'admin')
        ->call('save')
        ->assertHasErrors('name');
});

test('a permission that is not on the matrix is refused', function (): void {
    rolesAdmin();

    Livewire::test('admin.roles.edit')
        ->set('form.name', 'colado')
        // The client panel's key is not this matrix's to hand out.
        ->set('form.permissions', ['access-client-app'])
        ->call('save')
        ->assertHasErrors('permissions.0');
});

test('editing a role replaces what it opens, it does not add to it', function (): void {
    rolesAdmin();
    $support = Role::findByName('support');

    Livewire::test('admin.roles.edit', ['role' => $support->id])
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

test('the editor opens for the admin and closes for everyone else', function (): void {
    $support = Role::findByName('support');

    rolesAdmin();
    $this->get(route('admin.roles.create'))->assertOk()->assertSee('Nuevo rol');
    $this->get(route('admin.roles.edit', $support->id))->assertOk()->assertSee('Rol support');

    foreach (['support', 'client'] as $role) {
        Auth::logout();
        $person = User::factory()->create(['email_verified_at' => now()]);
        $person->syncRoles([$role]);
        $this->actingAs($person->refresh());

        $this->get(route('admin.roles.create'))->assertForbidden();
        $this->get(route('admin.roles.edit', $support->id))->assertForbidden();
    }
});

test('saving a new role keeps the screen on it and tells the editor what is stored', function (): void {
    rolesAdmin();

    $component = Livewire::test('admin.roles.edit')
        ->set('form.name', 'cobranza')
        ->set('form.permissions', ['payments.view', 'payments.verify'])
        ->call('save')
        ->assertDispatched('role-saved', permissions: ['payments.view', 'payments.verify']);

    $role = Role::findByName('cobranza');

    // The address changes to the new role so a reload edits it instead of writing it twice.
    expect($component->get('roleId'))->toBe($role->id)
        ->and($component->get('form.name'))->toBe('cobranza');
});

test('a starting point hands the editor its permissions and never the owner role', function (): void {
    rolesAdmin();

    $points = Access::startingPoints();

    expect($points)->toHaveKeys(['template:support', 'template:billing', 'template:moderation', 'role:support'])
        ->and($points)->not->toHaveKey('role:admin')
        ->and($points['template:billing']['permissions'])->toEqualCanonicalizing(['payments.view', 'payments.verify', 'businesses.view']);

    Livewire::test('admin.roles.edit')
        ->set('starter', 'template:billing')
        ->assertDispatched('role-loaded', permissions: $points['template:billing']['permissions'])
        ->set('starter', 'role:nobody')
        ->assertSet('form.permissions', []);
});

test('the roles list sends the people count to the users screen filtered by that role', function (): void {
    rolesAdmin();
    User::factory()->create(['email_verified_at' => now()])->assignRole('support');

    $this->get(route('admin.roles'))->assertOk()->assertSee(route('admin.users', ['rol' => 'support']), false);

    $other = User::factory()->create(['name' => 'Otra Persona', 'email_verified_at' => now()]);
    $other->assignRole(Role::findOrCreate('diseno', 'web')->syncPermissions(['access-admin-panel']));

    Livewire::test('admin.users.index')
        ->set('roleFilter', 'diseno')
        ->assertSee('Otra Persona')
        ->set('roleFilter', 'no-such-role')
        ->assertDontSee('Otra Persona');
});

test('the role history lists what was granted and taken, with who did it', function (): void {
    $admin = rolesAdmin();
    $admin->forceFill(['name' => 'Juan Quien Guarda'])->save();

    $role = Role::findOrCreate('diseno', 'web');
    $role->syncPermissions(['access-admin-panel', 'moderation.view']);
    $role->syncPermissions(['access-admin-panel', 'testimonials.moderate']);

    $person = User::factory()->create(['name' => 'Ana Con El Rol', 'email_verified_at' => now()]);
    $person->assignRole($role);
    $person->removeRole($role);

    // Somebody else's role being handed out must not leak into this one's trail.
    User::factory()->create(['name' => 'Beto Sin Relacion', 'email_verified_at' => now()])->assignRole('support');

    Livewire::test('admin.roles.edit', ['role' => $role->id])
        ->call('openHistory')
        ->assertSet('historyOpen', true)
        ->assertSee('Se lo asignó a Ana Con El Rol')
        ->assertSee('Se lo quitó a Ana Con El Rol')
        ->assertDontSee('Beto Sin Relacion')
        ->assertSee('Dio acceso')
        ->assertSee('Quitó acceso')
        ->assertSee('moderation.view')
        ->assertSee('Juan Quien Guarda')
        ->assertDontSee(Access::PANEL);
});

test('the role history is closed to a role that cannot read the audit', function (): void {
    $this->seed(MenuSeeder::class);
    $role = Role::findOrCreate('diseno', 'web');
    $role->syncPermissions(['access-admin-panel', 'roles.manage']);
    $person = User::factory()->create(['email_verified_at' => now()]);
    $person->assignRole($role);
    $this->actingAs($person->refresh());

    Livewire::test('admin.roles.edit', ['role' => $role->id])
        ->call('openHistory')
        ->assertForbidden();
});

test('the editor paints every permission of the matrix at least once', function (): void {
    $this->seed(MenuSeeder::class);

    $painted = collect(editorPermissions(Access::menuTree()));

    $assignable = collect(Access::matrix())->flatten();

    // A screen shared by two menu rows (Pagos and Cobranza) is one permission painted twice: that is the point.
    expect($painted->unique()->sort()->values()->all())->toBe($assignable->sort()->values()->all());
});

test('a screen that shares its permission says so and keeps the actions on the first one', function (): void {
    $this->seed(MenuSeeder::class);

    $billing = collect(Access::menuTree())->first(fn ($node) => $node->label === 'Cobros');
    $payments = collect($billing->children)->first(fn ($node) => $node->label === 'Pagos');
    $collections = collect($billing->children)->first(fn ($node) => $node->label === 'Cobranza');

    expect($payments->actions)->toBe(['payments.verify'])
        ->and($payments->sharedWith)->toBeNull()
        ->and($collections->sharedWith)->toBe('Pagos')
        ->and($collections->actions)->toBe([]);
});

test('every permission an action switch shows has a label', function (): void {
    $this->seed(MenuSeeder::class);

    foreach (editorPermissions(Access::menuTree(), actionsOnly: true) as $permission) {
        $key = 'admin.roles.permissions.'.$permission;

        expect(__($key))->not->toBe($key);
    }
});

test('the owner role is listed but marked as untouchable', function (): void {
    rolesAdmin();

    Livewire::test('admin.roles.index')
        ->assertSee('No se edita')
        ->assertSee('Abre todo');
});
