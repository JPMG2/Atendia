<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

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
