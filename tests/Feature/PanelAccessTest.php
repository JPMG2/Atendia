<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('a guest is redirected to login from the admin panel', function (): void {
    $this->get('/admin')->assertRedirect(route('login'));
});

test('a client cannot access the admin panel', function (): void {
    $client = User::factory()->create(); // factory asigna rol client

    $this->actingAs($client)->get('/admin')->assertForbidden();
});

test('an admin can access the admin panel', function (): void {
    $admin = User::factory()->create();
    $admin->syncRoles('admin');

    $this->actingAs($admin)->get('/admin')->assertOk();
});

test('a client can access the client dashboard', function (): void {
    $client = User::factory()->create();

    $this->actingAs($client)->get('/dashboard')->assertOk();
});

test('an admin can also access the client dashboard (super-admin)', function (): void {
    $admin = User::factory()->create();
    $admin->syncRoles('admin');

    $this->actingAs($admin)->get('/dashboard')->assertOk();
});

test('login lands an admin on the admin panel', function (): void {
    $admin = User::factory()->create();
    $admin->syncRoles('admin');

    $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
        ->assertRedirect(route('admin.dashboard', absolute: false));
});

test('login lands a client on the client dashboard', function (): void {
    $client = User::factory()->create();

    $this->post('/login', ['email' => $client->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard', absolute: false));
});

test('an admin sees a switch to the client panel from the admin panel', function (): void {
    $admin = User::factory()->create();
    $admin->syncRoles('admin');

    $this->actingAs($admin)->get('/admin')->assertSee('Ver panel cliente');
});

test('an admin sees a switch to the admin panel from the client dashboard', function (): void {
    $admin = User::factory()->create();
    $admin->syncRoles('admin');

    $this->actingAs($admin)->get('/dashboard')->assertSee('Panel admin');
});

test('a client sees no panel switch', function (): void {
    $client = User::factory()->create();

    $this->actingAs($client)->get('/dashboard')
        ->assertDontSee('Ver panel cliente')
        ->assertDontSee('Panel admin');
});
