<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The wizard forces nothing — what a person sees
|--------------------------------------------------------------------------
| What the catalog proposed and was already saved can be taken out again:
| every service has its cross, and Continuar keeps the list she left.
*/

beforeEach(function (): void {
    Queue::fake();
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('a proposed service that was already saved can be taken out of the list', function (): void {
    $business = Business::factory()->create(['name' => 'Clínica Vida']);
    Service::factory()->for($business)->create(['name' => 'Consulta clínica']);
    Service::factory()->for($business)->create(['name' => 'Control de rutina']);
    $user = User::factory()->create();
    $user->business()->associate($business)->save();
    $this->actingAs($user->refresh());

    $page = visit('/alta')->resize(390, 900);

    $page->script("Livewire.dispatch('wizard:step-completed', { step: 2 })");
    $page->click('[data-testid="wizard-tab-3"]')
        ->assertSeeIn('.wizard-pills', 'Consulta clínica')
        ->assertSeeIn('.wizard-pills', 'Control de rutina')
        ->assertPresent('.wizard-pill button')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'wizard-saved-services-removable');

    $page->click('[aria-label="Quitar"] >> nth=1')->wait(1);

    $page->assertDontSeeIn('.wizard-pills', 'Control de rutina')
        ->assertSeeIn('.wizard-pills', 'Consulta clínica');

    // Every mounted step has its own Continuar: only the visible one is this step's.
    $page->script("[...document.querySelectorAll('button')].filter(b => b.textContent.trim() === 'Continuar' && b.offsetParent !== null)[0].click()");
    $page->wait(2);

    expect($business->services()->pluck('name')->all())->toBe(['Consulta clínica']);
});
