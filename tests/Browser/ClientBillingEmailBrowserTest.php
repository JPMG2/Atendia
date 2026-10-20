<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Billing email, the contact card and the ended trial — the shots a human looks at
|--------------------------------------------------------------------------
| The address the charge notices go to is editable where the invoice data
| lives, the three data the profile meter counts are not called optional, and
| a trial that ran out unpaid says so on Mi plan.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);

    $this->business = Business::factory()->create(['name' => 'Clínica Vida', 'billing_email' => 'cobros@clinicavida.test']);
    $user = User::factory()->create(['name' => 'María González']);
    $user->business()->associate($this->business)->save();
    $this->actingAs($user->refresh());
});

test('the billing card shows the billing email and saving a new one sticks', function (): void {
    $page = visit(route('my-business.facturacion'))->resize(1280, 900);

    $page->assertNoJavaScriptErrors()
        ->assertSee('Correo de facturación')
        ->assertSee('A este correo llegan los avisos de cobro')
        ->assertValue('[name="billing_email"]', 'cobros@clinicavida.test')
        ->screenshot(filename: 'client-billing-email-desktop');

    $page->type('[name="billing_email"]', 'nuevo@clinicavida.test')
        ->click('Guardar')
        ->wait(2);

    expect($this->business->fresh()->billing_email)->toBe('nuevo@clinicavida.test');
});

test('a bad billing email is stopped on the screen before it travels', function (): void {
    $page = visit(route('my-business.facturacion'))->resize(1280, 900);

    $page->type('[name="billing_email"]', 'esto-no-es-un-correo')
        ->click('Guardar')
        ->wait(1)
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'client-billing-email-error');

    expect($this->business->fresh()->billing_email)->toBe('cobros@clinicavida.test');
});

test('the billing and contact cards hold on a phone in the dark', function (): void {
    visit(route('my-business.facturacion'))->resize(390, 1000)
        ->click('@theme-toggle')
        ->assertNoJavaScriptErrors()
        ->assertSee('Correo de facturación')
        ->screenshot(filename: 'client-billing-email-phone-dark');

    visit(route('my-business.contacto'))->resize(1280, 900)
        ->assertNoJavaScriptErrors()
        ->assertSee('Puedes guardar sin ellos')
        ->assertDontSee('WhatsApp para derivar · opcional')
        ->screenshot(filename: 'client-contact-desktop');
});

test('a trial that ran out unpaid says so on Mi plan', function (): void {
    $this->business->subscription->update(['trial_ends_at' => now()->subDays(2), 'current_period_ends_at' => null]);

    visit(route('my-plan'))->resize(1280, 900)
        ->assertNoJavaScriptErrors()
        ->assertSee('Tu prueba terminó')
        ->screenshot(filename: 'client-trial-over-desktop');
});
