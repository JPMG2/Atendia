<?php

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('the seeder creates admin and client roles with area permissions', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Role::where('name', 'admin')->exists())->toBeTrue()
        ->and(Role::where('name', 'client')->exists())->toBeTrue();

    $client = Role::findByName('client');
    expect($client->hasPermissionTo('access-client-app'))->toBeTrue()
        ->and($client->hasPermissionTo('access-admin-panel'))->toBeFalse();

    $admin = Role::findByName('admin');
    expect($admin->hasPermissionTo('access-admin-panel'))->toBeTrue()
        ->and($admin->hasPermissionTo('access-client-app'))->toBeTrue();
});

test('the admin seeder promotes the configured email and demotes previous admins', function (): void {
    config(['atendia.admin_email' => 'boss@atendia.test']);
    $this->seed(RolesAndPermissionsSeeder::class);

    $previous = User::factory()->create();
    $previous->assignRole('admin');
    $target = User::factory()->create(['email' => 'boss@atendia.test']);

    $this->seed(AdminUserSeeder::class);

    expect($target->fresh()->hasRole('admin'))->toBeTrue()
        ->and($previous->fresh()->hasRole('admin'))->toBeFalse()
        ->and($previous->fresh()->hasRole('client'))->toBeTrue();
});

test('the admin seeder is a no-op when the configured email has no user', function (): void {
    config(['atendia.admin_email' => 'ghost@atendia.test']);
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->seed(AdminUserSeeder::class);

    expect(User::role('admin')->count())->toBe(0);
});

test('a newly registered user receives the client role', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->post('/register', [
        'name' => 'Nuevo Cliente',
        'email' => 'nuevo@atendia.test',
        'password' => 'Secret123!',
        'password_confirmation' => 'Secret123!',
    ]);

    $user = User::where('email', 'nuevo@atendia.test')->first();
    expect($user)->not->toBeNull()
        ->and($user->hasRole('client'))->toBeTrue()
        ->and($user->hasRole('admin'))->toBeFalse();
});

test('an admin passes any gate via the super-admin Gate::before', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    expect($admin->can('a-permission-that-does-not-exist'))->toBeTrue();
});

test('a client does not pass an arbitrary gate', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $client = User::factory()->create();
    $client->assignRole('client');

    expect($client->can('access-admin-panel'))->toBeFalse()
        ->and($client->can('access-client-app'))->toBeTrue();
});
