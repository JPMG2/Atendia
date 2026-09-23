<?php

declare(strict_types=1);

use App\Models\LoginActivity;
use App\Models\LoginDevice;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// The settings page sits behind the client-panel lock: roles must exist.
beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
});

/**
 * The fingerprint the component computes for the test request, learned from
 * a probe instance so the assertions never hard-code the client's user agent.
 */
function currentTestFingerprint(User $user): string
{
    return Livewire::actingAs($user)->test('settings.section-devices')->instance()->currentFingerprint;
}

test('the devices card lists the devices and marks the current one', function (): void {
    $user = User::factory()->create();

    LoginDevice::factory()->for($user)->create([
        'fingerprint' => currentTestFingerprint($user),
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/120.0 Safari/537.36',
    ]);
    $other = LoginDevice::factory()->for($user)->create([
        'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0) Safari/604.1',
    ]);

    Livewire::actingAs($user)->test('settings.section-devices')
        ->assertSee(__('profile.devices.current'))
        ->assertSee('Chrome · Windows')
        ->assertSee('Safari · iOS')
        ->assertSee($other->ip);
});

test('revoking another device deletes it and toasts', function (): void {
    $user = User::factory()->create();
    $other = LoginDevice::factory()->for($user)->create();

    Livewire::actingAs($user)->test('settings.section-devices')
        ->call('revoke', $other->id)
        ->assertDispatched('notify', type: 'success');

    expect(LoginDevice::query()->whereKey($other->id)->exists())->toBeFalse();
});

test('the device in use refuses to revoke itself', function (): void {
    $user = User::factory()->create();
    $current = LoginDevice::factory()->for($user)->create([
        'fingerprint' => currentTestFingerprint($user),
    ]);

    Livewire::actingAs($user)->test('settings.section-devices')
        ->call('revoke', $current->id)
        ->assertDispatched('notify', type: 'warning');

    expect(LoginDevice::query()->whereKey($current->id)->exists())->toBeTrue();
});

test('a device of another account is out of reach', function (): void {
    $user = User::factory()->create();
    $foreign = LoginDevice::factory()->create();

    Livewire::actingAs($user)->test('settings.section-devices')
        ->assertDontSee($foreign->ip)
        ->call('revoke', $foreign->id);

    expect(LoginDevice::query()->whereKey($foreign->id)->exists())->toBeTrue();
});

test('closing every other session demands the right password', function (): void {
    $user = User::factory()->create();
    $other = LoginDevice::factory()->for($user)->create();

    Livewire::actingAs($user)->test('settings.section-devices')
        ->set('confirmingAll', true)
        ->set('password', 'not-the-password')
        ->call('revokeOthers')
        ->assertHasErrors('password');

    expect(LoginDevice::query()->whereKey($other->id)->exists())->toBeTrue();
});

test('closing every other session keeps only the device in use', function (): void {
    $user = User::factory()->create();
    $current = LoginDevice::factory()->for($user)->create([
        'fingerprint' => currentTestFingerprint($user),
    ]);
    LoginDevice::factory()->for($user)->count(2)->create();

    Livewire::actingAs($user)->test('settings.section-devices')
        ->set('confirmingAll', true)
        ->set('password', 'password')
        ->call('revokeOthers')
        ->assertDispatched('notify', type: 'success');

    expect($user->loginDevices()->get()->pluck('id')->all())->toBe([$current->id]);
});

test('a revoked device is logged out on its next request', function (): void {
    // Revocation only bites while the account still tracks other devices:
    // the browser whose row disappeared must die on its next request.
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    LoginDevice::factory()->for($user)->create();
    $user->loginDevices()->where('fingerprint', LoginDevice::fingerprintFor('Symfony'))->delete();

    $this->get('/ajustes')->assertRedirect(route('login'));
    $this->assertGuest();
});

test('a device from a location seen only once is flagged as unusual', function (): void {
    $user = User::factory()->create();
    LoginActivity::factory()->for($user)->create(['location' => 'Lima, Perú']);
    LoginDevice::factory()->for($user)->create(['location' => 'Lima, Perú']);

    Livewire::actingAs($user)->test('settings.section-devices')
        ->assertSee(__('profile.devices.unusual'));
});

test('a location the account visits twice stops being unusual', function (): void {
    // The flag heals itself: the second sign-in from a place makes it routine.
    $user = User::factory()->create();
    LoginActivity::factory()->for($user)->count(2)->create(['location' => 'Lima, Perú']);
    LoginDevice::factory()->for($user)->create(['location' => 'Lima, Perú']);

    Livewire::actingAs($user)->test('settings.section-devices')
        ->assertDontSee(__('profile.devices.unusual'));
});

test('the settings page opens with the security checkup', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/ajustes')
        ->assertSee(__('profile.checkup.title'))
        ->assertSee(__('profile.checkup.email_ok'))
        ->assertSee(__('profile.checkup.password'));
});

test('the checkup ring scores what actually varies', function (): void {
    // Verified mail but no sign-in trail yet: three of four. The first
    // recorded login completes the ring.
    $user = User::factory()->create();

    $this->actingAs($user)->get('/ajustes')->assertSee('3 de 4 al día');

    LoginActivity::factory()->for($user)->create();

    $this->actingAs($user)->get('/ajustes')->assertSee('4 de 4 al día');
});

test('an unverified email shows as pending in the checkup', function (): void {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get('/ajustes')
        ->assertSee(__('profile.checkup.email_pending'));
});

test('a known device browses untouched', function (): void {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    $this->get('/ajustes')->assertOk();
});
