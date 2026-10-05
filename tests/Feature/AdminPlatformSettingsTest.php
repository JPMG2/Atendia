<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\PlatformSetting;
use App\Models\User;
use Database\Seeders\PlatformSettingSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Ajustes de la plataforma (admin)
|--------------------------------------------------------------------------
| The knobs she may turn without a deploy. The row does not get read by the
| feature: it OVERRIDES `config('atendia.…')` at boot, which is what keeps
| the fifteen existing call sites untouched. So the test that matters is not
| "the row saved" but "the behaviour followed it".
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PlatformSettingSeeder::class);
});

function settingsAdmin(): User
{
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    return tap($admin->refresh(), fn (User $user) => test()->actingAs($user));
}

/** The form is keyed by ID, because a config path carries dots. */
function settingField(string $key): string
{
    return 'form.value.'.PlatformSetting::where('key', $key)->value('id');
}

function settingError(string $key): string
{
    return 'value.'.PlatformSetting::where('key', $key)->value('id');
}

test('the screen opens for the admin and closes for a client', function (): void {
    settingsAdmin();
    $this->get(route('admin.settings'))->assertOk();

    Auth::logout();
    $client = User::factory()->create(['email_verified_at' => now()]);
    $client->assignRole('client');
    $client->business()->associate(Business::factory()->create())->save();

    $this->actingAs($client->refresh())->get(route('admin.settings'))->assertForbidden();
});

test('every knob says what it does, not where it lives in the config', function (): void {
    settingsAdmin();

    Livewire::test('admin.settings.index')
        ->assertOk()
        ->assertSee('Silencio para dar por terminada una charla')
        ->assertSee('Horas sin que nadie escriba')
        // The config path is for the code, never for her.
        ->assertDontSee('analysis.idle_hours');
});

test('a saved knob overrides the config the whole app reads', function (): void {
    settingsAdmin();

    expect(config('atendia.analysis.idle_hours'))->toBe(2);

    Livewire::test('admin.settings.index')
        ->set(settingField('analysis.idle_hours'), '9')
        ->call('save')
        ->assertHasNoErrors();

    // What the next request boots with: the provider reads the rows and
    // writes them over the config, so nothing else had to change.
    Cache::forget('platform.settings');

    foreach (PlatformSetting::overrides() as $key => $value) {
        config()->set('atendia.'.$key, $value);
    }

    expect(config('atendia.analysis.idle_hours'))->toBe('9');
});

test('only the knobs she moved are written', function (): void {
    settingsAdmin();

    $untouched = PlatformSetting::where('key', 'billing.grace_days')->sole();
    $before = $untouched->updated_at;

    Livewire::test('admin.settings.index')
        ->set(settingField('analysis.idle_hours'), '7')
        ->call('save')
        ->assertHasNoErrors();

    expect($untouched->refresh()->updated_at->eq($before))->toBeTrue();
});

test('a value outside its bounds is refused instead of breaking a job', function (): void {
    settingsAdmin();

    // Zero quiet hours would read a live conversation as finished.
    Livewire::test('admin.settings.index')
        ->set(settingField('analysis.idle_hours'), '0')
        ->call('save')
        ->assertHasErrors(settingError('analysis.idle_hours'));

    expect(PlatformSetting::where('key', 'analysis.idle_hours')->value('value'))->toBe('2');
});

test('a time that is not a time is refused', function (): void {
    settingsAdmin();

    Livewire::test('admin.settings.index')
        ->set(settingField('schedule.whatsapp_digest'), '25:99')
        ->call('save')
        ->assertHasErrors(settingError('schedule.whatsapp_digest'));
});

test('restoring the defaults puts them on screen without writing them', function (): void {
    settingsAdmin();

    PlatformSetting::where('key', 'analysis.idle_hours')->update(['value' => '11']);

    Livewire::test('admin.settings.index')
        ->call('restoreDefaults')
        ->assertSet(settingField('analysis.idle_hours'), '2');

    // Nothing is written until she saves: changing her mind costs nothing.
    expect(PlatformSetting::where('key', 'analysis.idle_hours')->value('value'))->toBe('11');
});

test('saving with nothing changed says so instead of claiming a save', function (): void {
    settingsAdmin();

    Livewire::test('admin.settings.index')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('notify');
});

test('the seeder never overwrites a knob she already moved', function (): void {
    PlatformSetting::where('key', 'analysis.idle_hours')->update(['value' => '8']);

    $this->seed(PlatformSettingSeeder::class);

    expect(PlatformSetting::where('key', 'analysis.idle_hours')->value('value'))->toBe('8')
        ->and(PlatformSetting::where('key', 'analysis.idle_hours')->value('default_value'))->toBe('2');
});
