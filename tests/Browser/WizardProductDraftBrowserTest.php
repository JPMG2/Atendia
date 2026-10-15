<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Wizard products step — the name she typed, in a real browser
|--------------------------------------------------------------------------
| The box has no add button, only Enter. A name typed and followed straight
| by Continuar vanished: the step advanced and saved an empty list.
*/

test('the Agregar button puts the typed name in the list as a pill', function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);

    $business = Business::factory()->create(['name' => 'Pizzas de prueba']);
    $user = User::factory()->create();
    $user->business()->associate($business)->save();
    $user->syncRoles(['client']);
    $this->actingAs($user->refresh());

    $page = visit('/alta')->resize(1280, 900)
        ->click('[data-testid="wizard-tab-4"]')
        ->waitForText(__('wizard.products.manual'))
        ->type('[name="product_draft"]', 'Pizza de jamón')
        ->click('[wire\:click="add"]:visible')
        ->wait(1)
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'wizard-product-add-button-desktop');

    // Measured, not assumed: once the parent heard the event, the pill is
    // still there. The step used to be rebuilt empty at that moment.
    $pill = $page->script('(() => { const p = Array.from(document.querySelectorAll(".wizard-pill")).find(e => e.textContent.includes("Pizza de jamón") && e.offsetParent !== null); if (!p) { return null; } const r = p.getBoundingClientRect(); return { w: Math.round(r.width), h: Math.round(r.height) }; })()');

    expect($pill)->not->toBeNull()
        ->and($pill['w'])->toBeGreaterThan(40)
        ->and($pill['h'])->toBeGreaterThan(20);
});

test('a product typed and followed by Continuar is saved, not lost', function (): void {
    app()->setLocale('es');
    // Reconciling the offer publishes knowledge, whose indexing would call the embeddings API.
    Queue::fake();
    $this->seed(RolesAndPermissionsSeeder::class);

    $business = Business::factory()->create(['name' => 'Pizzas de prueba']);
    $user = User::factory()->create();
    $user->business()->associate($business)->save();
    $user->syncRoles(['client']);
    $this->actingAs($user->refresh());

    visit('/alta')->resize(1280, 900)
        ->assertNoJavaScriptErrors()
        ->click('[data-testid="wizard-tab-4"]')
        ->waitForText(__('wizard.products.manual'))
        ->type('[name="product_draft"]', 'Pizza de jamón')
        ->click('[wire\:click="finish"]:visible')
        ->wait(2)
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'wizard-product-draft-desktop');

    expect($business->products()->pluck('name')->all())->toBe(['Pizza de jamón']);
});
