<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The trail of who got the keys
|--------------------------------------------------------------------------
| The rest of the audit rides `LogsActivity` on a model, which cannot see
| this: granting a permission writes a PIVOT row and fires no model event.
| Without it the log said who edited a catalog but not who handed somebody
| the panel — the one change an audit is actually for.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);

    // The seeder grants too, with nobody signed in. Clearing here keeps each
    // test looking only at the change it made.
    Activity::query()->delete();
});

function auditAdmin(): User
{
    $admin = User::factory()->create(['name' => 'Juan', 'email_verified_at' => now()]);
    $admin->assignRole('admin');

    // Handing the admin its own role is itself a logged change, and it
    // happens before anybody is signed in: clearing leaves only the action
    // under test in the trail.
    Activity::query()->delete();

    return tap($admin->refresh(), fn (User $user) => test()->actingAs($user));
}

function accessTrail(): Collection
{
    return Activity::query()->where('log_name', 'access')->get();
}

test('granting a permission to a role is written down, with who did it', function (): void {
    $admin = auditAdmin();

    Livewire::test('admin.roles.index')
        ->call('create')
        ->set('form.name', 'call-center')
        ->set('form.permissions', ['support.view'])
        ->call('save')
        ->assertHasNoErrors();

    $granted = accessTrail()->firstWhere('description', 'granted');

    expect($granted)->not->toBeNull()
        ->and($granted->causer_id)->toBe($admin->id)
        ->and($granted->properties['what'])->toBe('permission')
        ->and($granted->properties['names'])->toContain('support.view');
});

test('taking a permission away is written down too', function (): void {
    auditAdmin();
    $support = Role::findByName('support');

    Livewire::test('admin.roles.index')
        ->call('edit', $support->id)
        // Leaves only logs.view: support.view and businesses.view go.
        ->set('form.permissions', ['logs.view'])
        ->call('save')
        ->assertHasNoErrors();

    $revoked = accessTrail()->firstWhere('description', 'revoked');

    expect($revoked)->not->toBeNull()
        ->and($revoked->properties['names'])->toContain('support.view');
});

test('handing a person a role is written down against that person', function (): void {
    $admin = auditAdmin();

    $person = User::factory()->create(['email_verified_at' => now()]);
    $person->assignRole('support');

    // The factory hands out `client` on creation, so the trail holds that one
    // too: the entry under test is the one naming the role we granted.
    $granted = accessTrail()
        ->first(fn (Activity $entry): bool => in_array('support', $entry->properties['names'], true));

    expect($granted)->not->toBeNull()
        ->and($granted->properties['what'])->toBe('role')
        ->and($granted->subject_id)->toBe($person->id)
        ->and($granted->causer_id)->toBe($admin->id);
});

test('the names are stored, never the ids the package passes around', function (): void {
    auditAdmin();

    $person = User::factory()->create(['email_verified_at' => now()]);
    $person->assignRole('support');

    $entry = accessTrail()
        ->first(fn (Activity $e): bool => in_array('support', $e->properties['names'], true));

    // An id means nothing to whoever reads the audit a year from now, and it
    // points at a row that may since have been renamed or deleted.
    expect($entry->properties['names'])->toBe(['support']);
});

/*
| The package hands a listener whatever shape it has at hand: an array of
| ids from `syncPermissions`, a single MODEL from `revokePermissionTo`. The
| screen only ever produced the first, so this one is the case the screen
| cannot reach — and the one that reported three permissions for a single
| revoke, because casting a model to an array returns its attributes.
*/
test('revoking one permission names that one, whatever shape the event carries', function (): void {
    auditAdmin();
    $role = Role::findOrCreate('temporal', 'web');
    $role->givePermissionTo('logs.view');

    Activity::query()->delete();
    $role->revokePermissionTo('logs.view');

    $revoked = accessTrail()->firstWhere('description', 'revoked');

    expect($revoked)->not->toBeNull()
        ->and($revoked->properties['names'])->toBe(['logs.view']);
});

test('a change made with nobody signed in still leaves its trail', function (): void {
    // The seeder and the console change access too; losing those entries
    // would make the trail lie by omission.
    $role = Role::findOrCreate('temporal', 'web');
    $role->givePermissionTo('logs.view');

    expect(accessTrail()->where('description', 'granted'))->not->toBeEmpty();
});
