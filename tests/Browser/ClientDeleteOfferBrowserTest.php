<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->setLocale('es');
    Queue::fake();
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->business = Business::factory()->create(['name' => 'Clínica Vida']);
    $user = User::factory()->create();
    $user->business()->associate($this->business)->save();
    $this->actingAs($user);
});

test('a service row carries a delete action that asks before it deletes', function (): void {
    $service = Service::factory()->for($this->business)->create(['name' => 'Cargado por error', 'is_active' => true]);

    $page = visit('/servicios')->resize(1280, 900);

    $page->assertSee('Cargado por error')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'client-service-row-delete');

    $page->script('document.querySelector("[aria-label=\'Eliminar Cargado por error\']").click()');

    $page->wait(1)
        ->assertSee('Esto no se puede deshacer')
        ->screenshot(filename: 'client-service-delete-confirm');

    $page->script('document.querySelector("[x-ref=accept]").click()');

    $page->wait(1)
        ->assertSee('Servicio eliminado')
        ->assertSee('Deshacer')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'client-service-delete-undo');

    expect(Service::query()->find($service->id))->toBeNull();

    $page->script('document.querySelector(".toast-action").click()');

    $page->wait(1)->assertSee('Servicio recuperado')->assertNoJavaScriptErrors();

    expect(Service::query()->find($service->id))->not->toBeNull();
});

test('a product row carries the same action on a phone', function (): void {
    Product::factory()->for($this->business)->create(['name' => 'Producto de prueba']);

    visit('/productos')->resize(390, 900)
        ->assertSee('Producto de prueba')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'client-product-row-delete-phone');
});
