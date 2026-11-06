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
| The wizard adds, it never removes — what a person sees
|--------------------------------------------------------------------------
| A saved service shows no cross. Only a name just typed, and not saved yet,
| carries one: a typo can be taken back, a stored service cannot.
*/

beforeEach(function (): void {
    Queue::fake();
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('a saved service has no cross in the services step and a name just typed does', function (): void {
    $business = Business::factory()->create(['name' => 'Clínica Vida']);
    Service::factory()->for($business)->create(['name' => 'Consulta clínica']);
    $user = User::factory()->create();
    $user->business()->associate($business)->save();
    $this->actingAs($user->refresh());

    $page = visit('/alta')->resize(390, 900);

    $page->script("Livewire.dispatch('wizard:step-completed', { step: 2 })");
    $page->click('[data-testid="wizard-tab-3"]')
        ->assertSee('Tus servicios')
        ->assertSeeIn('.wizard-pills', 'Consulta clínica')
        ->assertMissing('.wizard-pill button');

    $page->type('[name="service_draft"]', 'Consluta')->keys('[name="service_draft"]', 'Enter');

    $page->assertSeeIn('.wizard-pills', 'Consluta')
        ->assertPresent('.wizard-pill button')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'wizard-saved-service-no-cross');

    $page->click('.wizard-pill button');

    $page->assertDontSeeIn('.wizard-pills', 'Consluta')
        ->assertSeeIn('.wizard-pills', 'Consulta clínica');
});
