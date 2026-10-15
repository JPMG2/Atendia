<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\ServiceModality;
use App\Models\ServiceType;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The product type picker, in a real browser
|--------------------------------------------------------------------------
| The picker's options are an id => name array. A Pest component test sets
| the id straight on the property, so only a browser proves that the combobox
| sends the id and not the label (it sent "Plato" once and the sheet died).
*/

beforeEach(function (): void {
    app()->setLocale('es');
    // Saving the offer publishes knowledge, whose indexing calls the embeddings API.
    Queue::fake();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);

    $business = Business::factory()->create(['name' => 'Pizzas de prueba']);
    $user = User::factory()->create(['name' => 'Sergio']);
    $user->business()->associate($business)->save();
    $user->syncRoles(['client']);
    $this->actingAs($user->refresh());

    $modality = ServiceModality::factory()->create(['code' => 'producto']);
    $this->type = ServiceType::factory()->create([
        'name' => 'Plato',
        'service_modality_id' => $modality->id,
        'is_active' => true,
    ]);
});

test('picking a type saves the product with the type id, not its name', function (): void {
    $page = visit('/productos')->resize(1280, 900)
        ->assertNoJavaScriptErrors()
        ->click(__('client.products.add'))
        ->waitForText(__('client.products.field_type'))
        ->type('[name="name"]', 'Pizza a la piedra')
        ->click('.combo-control input[type="text"]')
        ->click('Plato')
        ->click(__('client.products.sheet_save'))
        ->wait(2)
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'product-type-picker-desktop');

    $product = Business::query()->where('name', 'Pizzas de prueba')->firstOrFail()
        ->products()->where('name', 'Pizza a la piedra')->first();

    expect($product)->not->toBeNull()
        ->and($product->service_type_id)->toBe($this->type->id);
});
