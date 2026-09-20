<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function customersClient(): User
{
    $user = User::factory()->create();
    $user->business()->associate(Business::factory()->create())->save();

    return $user;
}

test('guests are sent to the login', function (): void {
    $this->get(route('customers'))->assertRedirect(route('login'));
});

test('with no customers the screen explains itself', function (): void {
    $this->actingAs(customersClient());

    $this->get(route('customers'))
        ->assertSuccessful()
        ->assertSee(__('client.customers.empty_title'));
});

test('the directory lists my customers and never another tenant\'s', function (): void {
    $user = customersClient();
    Customer::factory()->create(['business_id' => $user->business_id, 'name' => 'María Pérez']);

    $stranger = customersClient();
    Customer::factory()->create(['business_id' => $stranger->business_id, 'name' => 'Ajena Total']);

    $this->actingAs($user);

    $this->get(route('customers'))
        ->assertSee('María Pérez')
        ->assertDontSee('Ajena Total');
});

test('the search narrows and paints what matched', function (): void {
    $user = customersClient();
    Customer::factory()->create(['business_id' => $user->business_id, 'name' => 'María Pérez']);
    Customer::factory()->create(['business_id' => $user->business_id, 'name' => 'Marcos Ruiz']);
    $this->actingAs($user);

    livewire('customers.index')
        ->set('search', 'perez')
        ->assertSee('María')
        ->assertDontSee('Marcos')
        ->assertSeeHtml('match-hit');
});

test('the audience filters carve the list', function (): void {
    $user = customersClient();
    Customer::factory()->create(['business_id' => $user->business_id, 'name' => 'Con Permiso', 'marketing_opt_in_at' => now()]);
    Customer::factory()->create(['business_id' => $user->business_id, 'name' => 'Cumple Hoy', 'birthday' => now()->subYears(30)->toDateString()]);
    Customer::factory()->create(['business_id' => $user->business_id, 'name' => 'Sin Nada']);
    $this->actingAs($user);

    livewire('customers.index')
        ->set('filter', 'optin')
        ->assertSee('Con Permiso')
        ->assertDontSee('Sin Nada')
        ->set('filter', 'birthday')
        ->assertSee('Cumple Hoy')
        ->assertDontSee('Con Permiso');
});

test('the sheet opens from a row and saves through the shared trait', function (): void {
    $user = customersClient();
    $customer = Customer::factory()->create(['business_id' => $user->business_id]);
    $this->actingAs($user);

    livewire('customers.index')
        ->call('show', $customer->id)
        ->assertSet('showCustomer', true)
        ->set('customerForm.name', 'María Pérez')
        ->call('saveCustomer');

    expect($customer->refresh()->name)->toBe('María Pérez');
});

test('another tenant\'s sheet cannot be opened even by id', function (): void {
    $stranger = customersClient();
    $foreign = Customer::factory()->create(['business_id' => $stranger->business_id, 'name' => 'Ajena Total']);

    $user = customersClient();
    Customer::factory()->create(['business_id' => $user->business_id]);
    $this->actingAs($user);

    livewire('customers.index')
        ->call('show', $foreign->id)
        ->assertSet('showCustomer', false);
});

test('the directory walks in tranches of fifteen', function (): void {
    $user = customersClient();

    foreach (range(1, 18) as $i) {
        Customer::factory()->create([
            'business_id' => $user->business_id,
            'name' => "Persona {$i}",
            'phone' => '54911'.str_pad((string) $i, 8, '0', STR_PAD_LEFT),
            'last_activity_at' => now()->subMinutes($i),
        ]);
    }

    $this->actingAs($user);

    livewire('customers.index')
        ->assertSee('Persona 1')
        ->assertDontSee('Persona 16')
        ->call('loadMore')
        ->assertSee('Persona 16');
});
