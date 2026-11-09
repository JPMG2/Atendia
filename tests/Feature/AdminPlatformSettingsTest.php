<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Company;
use App\Models\Country;
use App\Models\PlatformSetting;
use App\Models\Province;
use App\Models\Region;
use App\Models\User;
use Database\Seeders\PlatformSettingSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Once;
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
        ->assertDontSee('analysis.idle_hours')
        ->assertDontSee('admin.settings.keys.');

    $unworded = PlatformSetting::query()->pluck('key')
        ->reject(fn (string $key): bool => Lang::has('admin.settings.keys.'.$key.'.label') && Lang::has('admin.settings.keys.'.$key.'.hint'));

    expect($unworded->all())->toBe([]);
});

test('the hour of the mistakes mail names the city of the platform clock, from the company country', function (): void {
    $region = Region::factory()->for(Province::factory()->for(Country::factory()->state(['iso2' => 'VE'])))->create();
    Company::factory()->create(['region_id' => $region->id]);
    Once::flush();
    settingsAdmin();

    Livewire::test('admin.settings.index')
        ->assertSee('Va con la hora de Caracas, la de la plataforma');
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

test('the auto-responder phrases are a list she edits, one per line', function (): void {
    settingsAdmin();

    Livewire::test('admin.settings.index')
        ->assertSee(__('admin.settings.keys.bots.phrases.label'))
        ->set(settingField('bots.phrases'), "este es un mensaje automático\nvuelvo el lunes temprano")
        ->call('save')
        ->assertHasNoErrors();

    Cache::forget('platform.settings');

    foreach (PlatformSetting::overrides() as $key => $value) {
        config()->set('atendia.'.$key, $value);
    }

    expect(config('atendia.bots.phrases'))->toBe("este es un mensaje automático\nvuelvo el lunes temprano");
});

test('a phrase too short to tell a machine apart is refused, and an empty list is allowed', function (): void {
    settingsAdmin();

    Livewire::test('admin.settings.index')
        ->set(settingField('bots.phrases'), "este es un mensaje automático\nok")
        ->call('save')
        ->assertHasErrors([settingError('bots.phrases')]);

    Livewire::test('admin.settings.index')
        ->set(settingField('bots.phrases'), '')
        ->call('save')
        ->assertHasNoErrors();

    expect(PlatformSetting::where('key', 'bots.phrases')->value('value'))->toBe('');
});

test('a saved knob reaches the queue workers at once, not at their next restart', function (): void {
    settingsAdmin();
    Cache::forget('illuminate:queue:restart');

    Livewire::test('admin.settings.index')
        ->set(settingField('bots.burst_cap'), '20')
        ->call('save')
        ->assertHasNoErrors();

    expect(Cache::has('illuminate:queue:restart'))->toBeTrue();
});

test('reseeding keeps the file list as the default even when she edited hers', function (): void {
    PlatformSetting::where('key', 'bots.phrases')->update(['value' => 'solo esta']);
    config()->set('atendia.bots.phrases', 'solo esta');

    $this->seed(PlatformSettingSeeder::class);

    $row = PlatformSetting::where('key', 'bots.phrases')->sole();

    expect($row->value)->toBe('solo esta')
        ->and($row->default_value)->toContain('mensaje automático')
        ->and($row->defaultListSize)->toBeGreaterThan(10);
});
