<?php

declare(strict_types=1);

use App\Livewire\Forms\Admin\AdminUserForm;
use App\Mail\AccountEmailVerification;
use App\Mail\StaffTwoFactorDeadline;
use App\Models\Business;
use App\Models\LoginActivity;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Usuarios y accesos (admin)
|--------------------------------------------------------------------------
| The platform's OWN people, never its clients. The first version of this
| screen listed every row of `users`, so a business owner showed up as if
| she were staff — a screen lying about who holds the keys to the panel.
|
| The lock that matters: `admin` passes every gate through `Gate::before`,
| so it is seeder-only. The web may hand out the PANEL, never super-admin.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
});

function usersAdmin(): User
{
    $admin = User::factory()->create(['name' => 'Juan', 'email_verified_at' => now()]);
    $admin->assignRole('admin');

    return tap($admin->refresh(), fn (User $user) => test()->actingAs($user));
}

function staffNamed(string $name, string $email, string $role = 'support'): User
{
    $user = User::factory()->create(['name' => $name, 'email' => $email, 'email_verified_at' => now()]);
    $user->assignRole($role);

    return $user->refresh();
}

function businessOwner(string $name, string $email): User
{
    $user = User::factory()->create(['name' => $name, 'email' => $email, 'email_verified_at' => now()]);
    $user->assignRole('client');
    $user->business()->associate(Business::factory()->create())->save();

    return $user->refresh();
}

test('the screen opens for the admin and closes for a client', function (): void {
    usersAdmin();
    $this->get(route('admin.users'))->assertOk();

    Auth::logout();
    $client = businessOwner('Laura', 'laura@negocio.test');

    $this->actingAs($client)->get(route('admin.users'))->assertForbidden();
});

test('each person links to everything they did in the audit, only for who may read it', function (): void {
    usersAdmin();
    $rocio = staffNamed('Rocío Paz', 'rocio@atendia.test');

    Livewire::test('admin.users.index')
        ->assertSee('Ver historial')
        ->assertSeeHtml(e(route('admin.audit', ['quien' => $rocio->id, 'fuertes' => 0])));

    Auth::logout();
    $this->actingAs(staffNamed('Sin auditoría', 'sin@atendia.test', 'support'));

    Livewire::test('admin.users.index')->assertDontSee('Ver historial');
});

test('the owner reminds somebody from their row, and a second press says it was already done', function (): void {
    Mail::fake();
    Cache::flush();
    config()->set('atendia.security.staff_two_factor', ['grace_days' => 7, 'since' => '2026-01-01']);

    usersAdmin();
    $rocio = staffNamed('Rocío Paz', 'rocio@atendia.test');
    $rocio->forceFill(['created_at' => now()])->save();

    Livewire::test('admin.users.index')
        ->assertSeeHtml('data-testid="remind-'.$rocio->id.'"')
        ->call('remind', $rocio->id)
        ->assertDispatched('notify', type: 'success', message: 'Listo. Le avisamos a rocio@atendia.test que su plazo vence el '.now()->addDays(7)->format('d/m/Y').'.')
        ->call('remind', $rocio->id)
        ->assertDispatched('notify', type: 'info', message: 'rocio@atendia.test ya recibió el aviso hoy.');

    Mail::assertQueued(StaffTwoFactorDeadline::class, 1);
});

test('nobody is offered a reminder once they have the second step or are past the plazo', function (): void {
    config()->set('atendia.security.staff_two_factor', ['grace_days' => 7, 'since' => '2026-01-01']);

    usersAdmin();
    $covered = staffNamed('Con factor', 'con@atendia.test');
    $covered->forceFill(['two_factor_whatsapp_at' => now(), 'whatsapp' => '5492995550001'])->save();
    $late = staffNamed('Vencida', 'vencida@atendia.test');
    $late->forceFill(['created_at' => now()->subDays(30)])->save();

    Livewire::test('admin.users.index')
        ->assertDontSeeHtml('data-testid="remind-'.$covered->id.'"')
        ->assertDontSeeHtml('data-testid="remind-'.$late->id.'"');
});

test('a business owner is a client and never shows up here', function (): void {
    usersAdmin();
    businessOwner('Sergio Andrés', 'sergio@laboratoriovida.test');
    staffNamed('Rocío Soporte', 'rocio@atendia.test');

    Livewire::test('admin.users.index')
        ->assertSee('Rocío Soporte')
        // Listing her read as if she were staff. She lives in Negocios.
        ->assertDontSee('Sergio Andrés');
});

test('the list is whoever may open the panel, read from the permission', function (): void {
    usersAdmin();
    staffNamed('Rocío Soporte', 'rocio@atendia.test');

    expect(User::directory()->pluck('email')->all())
        ->toContain('rocio@atendia.test')
        ->toHaveCount(2);
});

test('super-admin is never offered as a role the web can hand out', function (): void {
    usersAdmin();

    // `admin` passes every gate: one stolen session would be the platform.
    expect(array_keys(AdminUserForm::assignableRoles()))
        ->not->toContain('admin')
        ->toContain('support');
});

test('asking for super-admin anyway is refused by the rules, not by the form', function (): void {
    usersAdmin();

    Livewire::test('admin.users.index')
        ->call('create')
        ->set('form.name', 'Alguien')
        ->set('form.email', 'alguien@atendia.test')
        ->set('form.role', 'admin')
        ->call('store')
        ->assertHasErrors('role');

    expect(User::where('email', 'alguien@atendia.test')->exists())->toBeFalse();
});

test('creating a user sends the access mail and sets no password for them', function (): void {
    Mail::fake();
    usersAdmin();

    Livewire::test('admin.users.index')
        ->call('create')
        ->set('form.name', 'Rocío Paz')
        ->set('form.email', 'rocio@atendia.test')
        ->set('form.role', 'support')
        ->call('store')
        ->assertHasNoErrors();

    $created = User::where('email', 'rocio@atendia.test')->sole();

    expect($created->hasPermissionTo('access-admin-panel'))->toBeTrue()
        ->and($created->email_verified_at)->toBeNull();

    Mail::assertQueued(AccountEmailVerification::class);
});

test('a closed account keeps its address reserved, so it cannot be reused', function (): void {
    usersAdmin();
    $gone = staffNamed('Se fue', 'sefue@atendia.test');
    $gone->delete();

    Livewire::test('admin.users.index')
        ->call('create')
        ->set('form.name', 'Otra persona')
        ->set('form.email', 'sefue@atendia.test')
        ->set('form.role', 'support')
        ->call('store')
        ->assertHasErrors('email');
});

test('someone without the permission cannot hand out a key to the panel', function (): void {
    $staff = staffNamed('Soporte sin permiso', 'rocio@atendia.test');
    $this->actingAs($staff);

    Livewire::test('admin.users.index')
        ->call('create')
        ->set('form.name', 'Colado')
        ->set('form.email', 'colado@atendia.test')
        ->set('form.role', 'support')
        ->call('store')
        ->assertForbidden();
});

test('the unverified account can be sent its access mail again', function (): void {
    Mail::fake();
    usersAdmin();

    $pending = staffNamed('Pendiente', 'pendiente@atendia.test');
    $pending->forceFill(['email_verified_at' => null])->save();

    Livewire::test('admin.users.index')
        ->call('resendVerification', $pending->id)
        ->assertHasNoErrors();

    Mail::assertQueued(AccountEmailVerification::class);
});

test('an address already verified is not mailed again', function (): void {
    Mail::fake();
    usersAdmin();
    $fine = staffNamed('Al día', 'aldia@atendia.test');

    Livewire::test('admin.users.index')->call('resendVerification', $fine->id);

    Mail::assertNothingQueued();
});

test('the last sign-in says where from, not only when', function (): void {
    usersAdmin();
    $staff = staffNamed('Rocío', 'rocio@atendia.test');

    LoginActivity::query()->forceCreate([
        'user_id' => $staff->id, 'ip' => '190.0.0.1', 'location' => 'Mérida, Venezuela',
        'user_agent' => 'probe', 'created_at' => now()->subDay(), 'updated_at' => now()->subDay(),
    ]);

    Livewire::test('admin.users.index')
        ->assertSee('Mérida, Venezuela')
        ->assertSee(now()->subDay()->format('d/m/Y'));
});

test('an account that never signed in says so instead of showing a blank', function (): void {
    usersAdmin();
    staffNamed('Nunca entró', 'nunca@atendia.test');

    Livewire::test('admin.users.index')->assertSee('Nunca entró');
});
