<?php

declare(strict_types=1);

use App\Classes\Main\AuditTrail;
use App\Models\Business;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Auditoría (admin)
|--------------------------------------------------------------------------
| It is opened to answer about a PERSON — "qué hizo Rocío" — never about a
| table. And it opens on the strong changes: when it was built, 757 of 848
| entries were the assistant filing suggestions, which buries the eighteen
| businesses somebody deleted.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    Activity::query()->delete();
});

function auditScreenAdmin(): User
{
    $admin = User::factory()->create(['name' => 'Juan', 'email_verified_at' => now()]);
    $admin->assignRole('admin');
    Activity::query()->delete();

    return tap($admin->refresh(), fn (User $user) => test()->actingAs($user));
}

test('the screen opens for the admin and closes for someone without the permission', function (): void {
    auditScreenAdmin();
    $this->get(route('admin.audit'))->assertOk();

    Auth::logout();
    $support = User::factory()->create(['email_verified_at' => now()]);
    $support->assignRole('support');

    $this->actingAs($support->refresh())->get(route('admin.audit'))->assertForbidden();
});

test('it opens on what does not get undone, not on the daily noise', function (): void {
    auditScreenAdmin();

    $kept = Business::factory()->create(['name' => 'Kiosco La Esquina']);
    $gone = Business::factory()->create(['name' => 'Panadería del Centro']);
    $gone->delete();

    Livewire::test('admin.audit.index')
        ->assertSet('onlyStrong', true)
        ->assertSee('Panadería del Centro')
        // A business created is an ordinary day; one deleted is not.
        ->assertDontSee('Kiosco La Esquina');
});

test('turning the filter off shows the ordinary movements too', function (): void {
    auditScreenAdmin();
    Business::factory()->create(['name' => 'Kiosco La Esquina']);

    Livewire::test('admin.audit.index')
        ->set('onlyStrong', false)
        ->assertSee('Kiosco La Esquina');
});

test('an access change is strong, because it hands out keys', function (): void {
    auditScreenAdmin();

    $person = User::factory()->create(['name' => 'Rocío Paz', 'email_verified_at' => now()]);
    $person->assignRole('support');

    Livewire::test('admin.audit.index')
        ->assertSee('Dio acceso')
        // Which key changed hands, not just that one did.
        ->assertSee('support');
});

test('the filter is the PERSON, which is how the question is asked', function (): void {
    $admin = auditScreenAdmin();

    $other = User::factory()->create(['name' => 'Rocío Paz', 'email_verified_at' => now()]);
    $other->assignRole('support');

    // What the admin did, versus what happened with nobody signed in.
    Auth::logout();
    Business::factory()->create(['name' => 'Sin dueño'])->delete();

    $this->actingAs($admin);

    Livewire::test('admin.audit.index')
        ->set('causer', (string) $admin->id)
        ->assertSee('Rocío Paz')
        ->assertDontSee('Sin dueño');
});

test('the system is a choice of its own, since most of the trail is its own', function (): void {
    auditScreenAdmin();

    Auth::logout();
    Business::factory()->create(['name' => 'Sin dueño'])->delete();

    $this->actingAs(User::query()->where('name', 'Juan')->sole());

    Livewire::test('admin.audit.index')
        ->set('causer', 'system')
        ->assertSee('Sin dueño')
        ->assertSee('El sistema');

    expect(AuditTrail::causerOptions())->toHaveKey('system');
});

test('an entry survives the thing it happened to', function (): void {
    auditScreenAdmin();

    $gone = Business::factory()->create(['name' => 'Panadería del Centro']);
    $gone->forceDelete();

    $entry = Activity::query()->where('description', 'deleted')->latest('id')->first();

    // The row outliving its subject is the point of an audit: it must still
    // read as something instead of as a blank.
    expect(AuditTrail::subjectOf($entry))->toContain('Negocio');
});

test('only the people who ever did something are offered as a filter', function (): void {
    auditScreenAdmin();

    $never = User::factory()->create(['name' => 'Nunca hizo nada', 'email_verified_at' => now()]);
    Activity::query()->delete();

    Business::factory()->create()->delete();

    expect(AuditTrail::causerOptions())->not->toHaveKey((string) $never->id);
});
