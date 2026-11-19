<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Company;
use App\Models\LoginDevice;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

const ANDROID_AGENT = 'Mozilla/5.0 (Linux; Android 14; SM-A155M) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Mobile Safari/537.36';
const WINDOWS_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36';

beforeEach(function (): void {
    app()->setLocale('es');

    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
    $this->seed(PlanSeeder::class);

    config()->set('services.evolution.url', 'http://evolution.test');
    config()->set('services.evolution.key', 'test-key');
});

test('a stolen phone is paused from the WhatsApp screen and the way back asks the password', function (int $width, bool $dark): void {
    $state = 'open';
    Http::fake(function (HttpRequest $request) use (&$state) {
        if (str_contains($request->url(), '/instance/logout/')) {
            $state = 'close';
        }

        return Http::response(['instance' => ['state' => $state]]);
    });

    $user = User::factory()->create(['password' => Hash::make('Clave-Vieja-2026!')]);
    $user->assignRole('client');
    $user->business()->associate(Business::factory()->create([
        'name' => 'Laboratorio Vida',
        'whatsapp_instance' => 'business-9',
        'whatsapp_connected_at' => now(),
    ]))->save();
    $agent = User::factory()->create(['name' => 'Lucía Recepción', 'business_id' => $user->business_id]);
    $agent->assignRole('agent');
    LoginDevice::factory()->for($agent)->create([
        'fingerprint' => LoginDevice::fingerprintFor(ANDROID_AGENT),
        'user_agent' => ANDROID_AGENT,
        'last_login_at' => now()->subHours(3),
    ]);
    LoginDevice::factory()->for($agent)->create([
        'fingerprint' => LoginDevice::fingerprintFor(WINDOWS_AGENT),
        'user_agent' => WINDOWS_AGENT,
        'last_login_at' => now()->subDays(2),
    ]);
    $this->actingAs($user);

    $page = visit('/whatsapp')->resize($width, 900);

    if ($dark) {
        $page->click('@theme-toggle');
    }

    $page->assertNoJavaScriptErrors()
        ->assertSee(__('whatsapp.lost_phone.title'))
        ->screenshot(filename: 'lost-phone-offer-'.$width.'-'.($dark ? 'dark' : 'light'))
        ->type('current_password', 'Clave-Vieja-2026!')
        ->type('password', 'Clave-Nueva-2026!')
        ->type('password_confirmation', 'Clave-Nueva-2026!')
        ->click(__('whatsapp.lost_phone.button'))
        ->assertSee(__('whatsapp.lost_phone.confirm_title'))
        ->screenshot(filename: 'lost-phone-ask-'.$width.'-'.($dark ? 'dark' : 'light'))
        ->click(__('whatsapp.lost_phone.confirm_accept'))
        ->assertSee(__('whatsapp.lost_phone.paused_title'))
        ->assertSee(__('whatsapp.lost_phone.banner_title'))
        ->assertSee(__('whatsapp.lost_phone.cut_title'))
        ->assertSee('Lucía Recepción')
        ->assertSee('Chrome · Android')
        ->assertDontSee(__('whatsapp.disconnect.button'))
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'lost-phone-paused-'.$width.'-'.($dark ? 'dark' : 'light'));

    expect($user->business->fresh()->isPausedForLostPhone())->toBeTrue();

    // The session that paused survived its own pause: it is the one lifting it, with the new password.
    $page->type('current_password', 'Clave-Nueva-2026!')
        ->click(__('whatsapp.lost_phone.resume_button'))
        ->assertSee(__('whatsapp.connect.title'))
        ->assertDontSee(__('whatsapp.lost_phone.banner_title'));

    expect($user->business->fresh()->isPausedForLostPhone())->toBeFalse();
})->with([[1280, false], [390, true]]);

test('the login offers the lost-phone way in and says where it leads', function (int $width, bool $dark): void {
    $page = visit('/login')->resize($width, 900);

    if ($dark) {
        // The guest layout's toggle has no test id; the stored choice also survives the redirect.
        $page->script("localStorage.setItem('atendia-theme', 'dark'); document.documentElement.classList.add('dark')");
    }

    $page->assertNoJavaScriptErrors()
        ->assertSee(__('auth.login.lost_phone'))
        ->screenshot(filename: 'lost-phone-login-'.$width.'-'.($dark ? 'dark' : 'light'))
        ->click(__('auth.login.lost_phone'))
        ->assertSee(__('auth.login.lost_phone_note'))
        ->assertDontSee(__('auth.login.lost_phone'))
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'lost-phone-login-note-'.$width.'-'.($dark ? 'dark' : 'light'));
})->with([[1280, true], [390, false]]);

test('a WhatsApp login code offers support when the phone is gone', function (int $width, bool $dark): void {
    Http::fake(['http://evolution.test/*' => Http::response(['status' => 'PENDING'])]);
    Company::factory()->create(['brand_name' => 'Aliadito', 'email' => 'ayuda@plataforma.test']);
    $business = Business::factory()->create(['fallback_whatsapp_number' => '+58 412 555 4455']);
    $owner = User::factory()->create([
        'business_id' => $business->id,
        'password' => Hash::make('Clave-Vieja-2026!'),
        'two_factor_whatsapp_at' => now(),
    ]);
    $owner->assignRole('client');
    LoginDevice::factory()->for($owner)->create();

    $page = visit('/login')->resize($width, 900);

    if ($dark) {
        $page->script("localStorage.setItem('atendia-theme', 'dark'); document.documentElement.classList.add('dark')");
    }

    $page->type('email', $owner->email)
        ->type('password', 'Clave-Vieja-2026!')
        ->click(__('auth.login.submit'))
        ->assertSee(__('security.challenge_whatsapp.title'))
        ->assertSee(__('security.challenge.no_phone'))
        ->assertSee(__('security.challenge.no_phone_cta'))
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'lost-phone-challenge-'.$width.'-'.($dark ? 'dark' : 'light'));
})->with([[1280, true], [390, false]]);

test('the admin directory marks a paused number on its row and its ficha', function (int $width, bool $dark): void {
    Company::factory()->create(['brand_name' => 'Aliadito']);
    Business::factory()->create(['name' => 'Taller Luna']);
    $paused = Business::factory()->create(['name' => 'Laboratorio Vida']);
    $paused->forceFill(['lost_phone_paused_at' => now()->subHours(5)])->save();
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $this->actingAs($admin->refresh());

    $page = visit(route('admin.businesses'))->resize($width, 900);

    if ($dark) {
        $page->click('@theme-toggle');
    }

    $page->assertNoJavaScriptErrors()
        ->assertPresent('[data-testid="biz-paused-'.$paused->id.'"]')
        ->screenshot(filename: 'lost-phone-admin-row-'.$width.'-'.($dark ? 'dark' : 'light'))
        ->click('[data-testid="biz-open-'.$paused->id.'"]')
        ->assertPresent('[data-testid="biz-lost-phone-'.$paused->id.'"]')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'lost-phone-admin-ficha-'.$width.'-'.($dark ? 'dark' : 'light'));
})->with([[1280, false], [390, true]]);

test('the admin pauses for an owner who asked support', function (int $width, bool $dark): void {
    Http::fake(fn () => Http::response(['instance' => ['state' => 'close']]));
    Company::factory()->create(['brand_name' => 'Aliadito']);
    $business = Business::factory()->create(['name' => 'Taller Luna', 'whatsapp_instance' => 'business-7', 'whatsapp_connected_at' => now()]);
    $owner = User::factory()->create(['business_id' => $business->id]);
    $owner->assignRole('client');
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $this->actingAs($admin->refresh());

    $page = visit(route('admin.businesses', ['negocio' => $business->id]))->resize($width, 900);

    if ($dark) {
        $page->click('@theme-toggle');
    }

    $page->assertPresent('[data-testid="biz-pause-'.$business->id.'"]')
        ->screenshot(filename: 'lost-phone-admin-pause-'.$width.'-'.($dark ? 'dark' : 'light'))
        ->click('[data-testid="biz-pause-'.$business->id.'"]')
        ->assertSee(__('admin.businesses.lost_phone.accept'))
        ->screenshot(filename: 'lost-phone-admin-pause-ask-'.$width.'-'.($dark ? 'dark' : 'light'))
        ->click('[x-ref="accept"]')
        ->assertPresent('[data-testid="biz-lost-phone-'.$business->id.'"]')
        ->assertNoJavaScriptErrors();

    expect($business->fresh()->isPausedForLostPhone())->toBeTrue();
})->with([[1280, true], [390, false]]);

test('the signup WhatsApp step shows the pause instead of a QR', function (): void {
    $user = User::factory()->create([
        'business_id' => Business::factory()->create(['lost_phone_paused_at' => now()])->id,
        'password' => Hash::make('Clave-Nueva-2026!'),
    ]);
    $user->assignRole('client');

    // A login made during the pause is the session that may see it.
    $page = visit('/login')->resize(1280, 900)
        ->type('email', $user->email)
        ->type('password', 'Clave-Nueva-2026!')
        ->click(__('auth.login.submit'))
        ->navigate('/alta');
    $page->assertPresent('.wizard-panel')->wait(1);
    $page->script("window.Livewire.getByName('business.wizard')[0].goToStep(5)");
    $page->wait(1);

    $page->assertSee(__('whatsapp.lost_phone.banner_title'))
        ->assertSee(__('whatsapp.lost_phone.banner_cta'))
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'lost-phone-wizard-1280-light');
});
