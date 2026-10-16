<?php

declare(strict_types=1);

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Deleting a service or a product from the client's own lists
|--------------------------------------------------------------------------
| Pause and stock already stopped offering things. Delete is for what was
| loaded by mistake: it goes when the business has no history on it, and
| a service with an appointment is kept and told to be paused instead.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    // The offer publishes its knowledge on every change, which would index.
    Queue::fake();

    $this->user = User::factory()->create(['business_id' => Business::factory()->create()->id])->refresh();
    $this->actingAs($this->user);
});

test('a service nobody booked is deleted from its row', function (): void {
    $service = Service::factory()->for($this->user->business)->create(['name' => 'Cargado por error']);

    Livewire::test('goods.index')
        ->call('remove', $service->id)
        ->assertDispatched('notify', type: 'success', message: __('client.services.deleted'));

    expect(Service::query()->find($service->id))->toBeNull();
});

test('the deleted service comes back from the toast undo', function (): void {
    $service = Service::factory()->for($this->user->business)->create(['name' => 'Se arrepintió']);

    Livewire::test('goods.index')
        ->call('remove', $service->id)
        ->assertDispatched('notify', fn (string $event, array $params): bool => ($params['action']['dispatch'] ?? null) === 'service-restore'
            && ($params['action']['params']['id'] ?? null) === $service->id)
        ->dispatch('service-restore', id: $service->id)
        ->assertDispatched('notify', type: 'success', message: __('client.services.restored'));

    expect(Service::query()->find($service->id))->not->toBeNull();
});

test('the deleted product comes back from the toast undo', function (): void {
    $product = Product::factory()->for($this->user->business)->create(['name' => 'Se arrepintió']);

    Livewire::test('products.index')
        ->call('remove', $product->id)
        ->dispatch('product-restore', id: $product->id)
        ->assertDispatched('notify', type: 'success', message: __('client.products.restored'));

    expect(Product::query()->find($product->id))->not->toBeNull();
});

test('a service with an appointment is kept and the reason is said', function (): void {
    $service = Service::factory()->for($this->user->business)->create();
    Appointment::factory()->create([
        'business_id' => $this->user->business_id,
        'service_id' => $service->id,
    ]);

    Livewire::test('goods.index')
        ->call('remove', $service->id)
        ->assertDispatched('notify', type: 'warning', message: __('client.services.delete_held'));

    expect(Service::query()->find($service->id))->not->toBeNull();
});

test('a product is deleted from its row', function (): void {
    $product = Product::factory()->for($this->user->business)->create(['name' => 'Cargado por error']);

    Livewire::test('products.index')
        ->call('remove', $product->id)
        ->assertDispatched('notify', type: 'success', message: __('client.products.deleted'));

    expect(Product::query()->find($product->id))->toBeNull();
});

test('one business cannot delete the service of another', function (): void {
    $stranger = Service::factory()->for(Business::factory()->create())->create();

    Livewire::test('goods.index')
        ->call('remove', $stranger->id)
        ->assertDispatched('notify', type: 'error');

    expect(Service::withoutGlobalScopes()->find($stranger->id))->not->toBeNull();
});
