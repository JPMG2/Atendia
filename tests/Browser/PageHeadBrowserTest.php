<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
| Every client screen wears the same page head component: title, subtitle,
| and whatever rides beside it (back arrow, avatar, pill), on desktop and phone.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create(['name' => 'María González']);
    $user->business()->associate(Business::factory()->create(['name' => 'Clínica Vida']))->save();
    $this->actingAs($user);
});

test('the home head keeps the connection pill beside the greeting and leads to whatsapp', function (): void {
    $page = visit('/dashboard')->resize(1280, 900);

    $page->assertNoJavaScriptErrors()
        ->assertSee('Hola, María González')
        ->assertSee('Clínica Vida · '.__('client.home.disconnected'))
        ->screenshot(filename: 'page-head-home-desktop')
        ->click('Clínica Vida · '.__('client.home.disconnected'))
        ->assertPathIs('/whatsapp')
        ->assertSee(__('whatsapp.title'))
        ->assertNoJavaScriptErrors();
});

test('heads with a back arrow and an avatar read on a phone, in both themes', function (): void {
    visit('/negocio')->resize(560, 900)
        ->assertNoJavaScriptErrors()
        ->assertSee(__('client.business.title'))
        ->screenshot(filename: 'page-head-business-phone');

    visit('/ajustes')->resize(560, 900)
        ->assertNoJavaScriptErrors()
        ->assertSee(__('settings.title'))
        ->click('@theme-toggle')
        ->screenshot(filename: 'page-head-settings-phone-dark');
});

test('the far-side slot sits at the right edge of the head', function (): void {
    visit('/plan')->resize(1280, 900)
        ->assertNoJavaScriptErrors()
        ->assertSee(__('plan.title'))
        ->screenshot(filename: 'page-head-plan-desktop');
});
