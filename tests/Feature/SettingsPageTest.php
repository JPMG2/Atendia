<?php

declare(strict_types=1);

use App\Mail\AccountPasswordChanged;
use App\Models\LoginDevice;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| "Ajustes" — the account settings page
|--------------------------------------------------------------------------
| The page replaced Breeze's /profile: it sits behind the client-panel lock,
| renders every card (or one, through its deep link), and the profile and
| password cards save through the Account piece with re-authentication.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    Mail::fake();
});

test('the settings page sits behind the client panel lock', function (): void {
    $this->get(route('settings'))->assertRedirect(route('login'));

    $stranger = User::factory()->create();
    $stranger->syncRoles([]);

    $this->actingAs($stranger->refresh())->get(route('settings'))->assertForbidden();
});

test('the full page shows every card and the on-page index', function (): void {
    $this->actingAs(User::factory()->create()->refresh());

    $this->get(route('settings'))
        ->assertSuccessful()
        ->assertSee(__('settings.profile.title'))
        ->assertSee(__('settings.email.title'))
        ->assertSee(__('settings.password.title'))
        ->assertSee(__('profile.devices.title'))
        ->assertSee(__('profile.activity.title'))
        ->assertSee(__('settings.close.title'))
        ->assertSee(__('settings.on_this_page'));
});

test('a deep link renders only its own card', function (): void {
    $this->actingAs(User::factory()->create()->refresh());

    $this->get(route('settings.contrasena'))
        ->assertSuccessful()
        ->assertSee(__('settings.password.submit'))
        ->assertDontSee(__('settings.email.submit'));
});

test('the old breeze address redirects to the new page', function (): void {
    $this->get('/profile')->assertRedirect('/ajustes');
});

test('the profile card renames the account', function (): void {
    $user = User::factory()->create(['name' => 'Old Name']);
    $this->actingAs($user->refresh());

    Livewire::test('settings.section-profile')
        ->assertSet('form.name', 'Old Name')
        ->set('form.name', 'María González')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('notify');

    expect($user->refresh()->name)->toBe('María González');
});

test('the profile card refuses an empty or marked-up name', function (string $name): void {
    $this->actingAs(User::factory()->create()->refresh());

    Livewire::test('settings.section-profile')
        ->set('form.name', $name)
        ->call('save')
        ->assertHasErrors('name');
})->with(['empty' => '', 'markup' => '<script>x</script>']);

test('changing the password demands the current one', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user->refresh());

    Livewire::test('settings.section-password')
        ->set('form.current_password', 'wrong-password')
        ->set('form.password', 'Nueva#Clave2026')
        ->set('form.password_confirmation', 'Nueva#Clave2026')
        ->call('save')
        ->assertHasErrors('current_password');

    expect(Hash::check('password', $user->refresh()->password))->toBeTrue();
    Mail::assertNothingQueued();
});

test('the current-password check locks after five misses', function (): void {
    $this->actingAs(User::factory()->create()->refresh());

    $component = Livewire::test('settings.section-password')
        ->set('form.password', 'Nueva#Clave2026')
        ->set('form.password_confirmation', 'Nueva#Clave2026');

    foreach (range(1, 5) as $ignored) {
        $component->set('form.current_password', 'wrong')->call('save');
    }

    // Even the right password bounces while the lock holds.
    $component->set('form.current_password', 'password')->call('save')->assertHasErrors('current_password');

    expect($component->errors()->first('current_password'))->not->toBe(__('validation.current_password'));
});

test('a weak new password is refused by the app-wide policy', function (): void {
    $this->actingAs(User::factory()->create()->refresh());

    Livewire::test('settings.section-password')
        ->set('form.current_password', 'password')
        ->set('form.password', 'weakpass')
        ->set('form.password_confirmation', 'weakpass')
        ->call('save')
        ->assertHasErrors('password');
});

test('a password change saves, mails a receipt and can close the other sessions', function (): void {
    $user = User::factory()->create();
    // The fingerprint Livewire's test requests carry, read the way the devices card does.
    $current = Livewire::actingAs($user)->test('settings.section-devices')->instance()->currentFingerprint;
    LoginDevice::factory()->for($user)->create(['fingerprint' => $current]);
    LoginDevice::factory()->for($user)->create(['fingerprint' => 'another-browser']);
    $this->actingAs($user->refresh());

    Livewire::test('settings.section-password')
        ->set('form.current_password', 'password')
        ->set('form.password', 'Nueva#Clave2026')
        ->set('form.password_confirmation', 'Nueva#Clave2026')
        ->set('form.logout_others', true)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('form.password', '');

    expect(Hash::check('Nueva#Clave2026', $user->refresh()->password))->toBeTrue()
        ->and($user->loginDevices()->pluck('fingerprint')->all())->toBe([$current]);

    Mail::assertQueued(AccountPasswordChanged::class, fn ($mail): bool => $mail->hasTo($user->email));
});

test('the account mails render their copy', function (): void {
    $user = User::factory()->create(['name' => 'Ana']);

    expect((new AccountPasswordChanged($user))->render())
        ->toContain(__('mail.account.password_changed.title'))
        ->toContain(route('password.request'));
});
