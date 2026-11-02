<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The customer fiche as an agent and as the owner — the shots a human looks at
|--------------------------------------------------------------------------
| The agent's fiche keeps everything to attend (data, notes, what the customer
| said about offers) and loses the merge, the permit button and Exportar.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
    $this->seed(PlanSeeder::class);
});

test('the customer fiche shows the owner buttons only to the owner', function (string $who, int $width, bool $dark): void {
    $business = Business::factory()->create(['name' => 'Consultorio Vida']);
    $owner = User::factory()->create(['business_id' => $business->id]);
    $owner->assignRole('client');
    $agent = User::factory()->create(['business_id' => $business->id]);
    $agent->syncRoles(['agent']);
    Customer::factory()->create(['business_id' => $business->id, 'name' => 'Ana Gómez', 'email' => 'ana@mail.test']);
    Customer::factory()->create(['business_id' => $business->id, 'name' => 'Ana G.', 'email' => 'ana@mail.test']);

    $this->actingAs(($who === 'agent' ? $agent : $owner)->refresh());
    $page = visit('/clientes')->resize($width, 900);

    if ($dark) {
        $page->click('@theme-toggle');
    }

    $page->assertNoJavaScriptErrors()->assertSee('Ana Gómez');
    $who === 'agent' ? $page->assertDontSee('Exportar') : $page->assertSee('Exportar');

    $page->click('Ana Gómez')->assertSee(__('client.customers.field_notes'));

    if ($who === 'agent') {
        $page->assertDontSee(__('client.customers.merge_button'))->assertDontSee(__('client.customers.opt_in_button'));
    } else {
        $page->assertSee(__('client.customers.merge_button'))->assertSee(__('client.customers.opt_in_button'));
    }

    $page->screenshot(filename: 'customer-fiche-'.$who.'-'.$width.'-'.($dark ? 'dark' : 'light'));
})->with([
    ['agent', 1280, false],
    ['owner', 1280, false],
    ['agent', 390, true],
]);
