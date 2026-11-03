<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\PlatformSetting;
use Database\Seeders\PlatformSettingSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;

uses(DatabaseTransactions::class);

/*
|--------------------------------------------------------------------------
| The oldest phone build the API still serves
|--------------------------------------------------------------------------
| A store install cannot be fixed from the server, so the admin sets a floor
| and an older build hears "update" before any data, sign-in included.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    Company::factory()->create(['brand_name' => 'Aliadito']);
});

it('serves every build, and one that says none, while the floor is 0', function (): void {
    config(['atendia.app.min_build' => 0]);

    $this->getJson('/api/v1/brand')->assertOk();
    $this->withHeaders(['X-App-Build' => '1'])->getJson('/api/v1/brand')->assertOk();
});

it('answers update, with its words, to a build under the floor and to one that says none', function (string $sent, int $seen): void {
    config(['atendia.app.min_build' => 4]);

    $headers = $sent === '' ? [] : ['X-App-Build' => $sent];

    $this->withHeaders($headers)->getJson('/api/v1/brand')
        ->assertStatus(426)
        ->assertJsonPath('code', 'app_update_required')
        ->assertJsonPath('message', __('api.update.message'))
        ->assertJsonPath('update.title', 'Actualiza '.Company::brand())
        ->assertJsonPath('update.action', __('api.update.action'))
        ->assertJsonPath('update.detail', __('api.update.detail', ['build' => $seen, 'min' => 4]));
})->with([
    'an older build' => ['3', 3],
    'no build at all' => ['', 0],
    'a build that is not a number' => ['4.0', 0],
]);

it('serves the floor build and anything newer', function (string $sent): void {
    config(['atendia.app.min_build' => 4]);

    $this->withHeaders(['X-App-Build' => $sent])->getJson('/api/v1/brand')->assertOk();
})->with(['4', '12']);

it('stops an outdated phone at the sign-in and at a signed-in screen', function (): void {
    config(['atendia.app.min_build' => 4]);
    $user = payingUser();

    $this->withHeaders(apiDeviceHeaders() + ['X-App-Build' => '3'])
        ->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'password', 'device_name' => 'Pixel'])
        ->assertStatus(426);

    $token = appInstallToken($user);

    $this->withToken($token)->withHeaders(apiDeviceHeaders() + ['X-App-Build' => '3'])->getJson('/api/v1/me')->assertStatus(426);
    $this->withToken($token)->withHeaders(apiDeviceHeaders() + ['X-App-Build' => '4'])->getJson('/api/v1/me')->assertOk();
});

it('says update before the token check, so a phone whose session died hears update and not "signed out"', function (): void {
    config(['atendia.app.min_build' => 4]);

    $this->withToken('1|not-a-token')->withHeaders(apiDeviceHeaders() + ['X-App-Build' => '3'])
        ->getJson('/api/v1/me')
        ->assertStatus(426);
});

it('leaves the ways out open to an outdated phone: signing out', function (): void {
    config(['atendia.app.min_build' => 4]);
    $user = payingUser();

    $this->withToken(appInstallToken($user))->withHeaders(apiDeviceHeaders() + ['X-App-Build' => '3'])
        ->postJson('/api/v1/logout')
        ->assertOk();

    expect($user->tokens()->count())->toBe(0);
});

it('judges only Android by the Play Console number: an iPhone and the test web build are let through', function (string $platform): void {
    config(['atendia.app.min_build' => 40]);

    $this->withHeaders(['X-App-Build' => '2', 'X-App-Platform' => $platform])->getJson('/api/v1/brand')->assertOk();
    $this->withHeaders(['X-App-Build' => '2', 'X-App-Platform' => 'android'])->getJson('/api/v1/brand')->assertStatus(426);
})->with(['ios', 'web']);

it('takes the floor from the admin setting the boot override reads', function (): void {
    $this->seed(PlatformSettingSeeder::class);

    $row = PlatformSetting::where('key', 'app.min_build')->sole();
    expect($row->group)->toBe('app')->and($row->value)->toBe('0');

    $row->update(['value' => '7']);

    // What the next request boots with, as AppServiceProvider::applyPlatformSettings does it.
    Cache::forget('platform.settings');
    foreach (PlatformSetting::overrides() as $key => $value) {
        config()->set('atendia.'.$key, $value);
    }

    $this->withHeaders(['X-App-Build' => '6'])->getJson('/api/v1/brand')->assertStatus(426);
    $this->withHeaders(['X-App-Build' => '7'])->getJson('/api/v1/brand')->assertOk();
});
