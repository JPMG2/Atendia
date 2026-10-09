<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| "4 of 34": a count that says what the filter holds back
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create(['name' => 'Juan', 'email_verified_at' => now()]);
    $admin->assignRole('admin');
    $this->actingAs($admin->refresh());
});

test('businesses count the whole while nothing is filtered, and "of" the whole when something is', function (): void {
    foreach (['Aurora', 'Brisa', 'Zafiro'] as $name) {
        Business::factory()->create(['name' => $name]);
    }

    Livewire::test('admin.businesses.index')->assertSee('3 negocios')->assertDontSee(' de 3 negocios');

    Livewire::test('admin.businesses.index')->set('search', 'Aurora')->assertSee('1 de 3 negocios');
});

test('people count "of" the whole when the search holds some back', function (): void {
    $other = User::factory()->create(['name' => 'Rocío Paz', 'email_verified_at' => now()]);
    $other->assignRole('support');

    Livewire::test('admin.users.index')->assertSee('2 personas')
        ->set('search', 'Rocío')->assertSee('1 de 2 personas');
});

test('the audit counts against the whole trail, which its 200-row cap and its filter both hide', function (): void {
    Business::factory()->count(2)->create();
    Business::factory()->create()->delete();

    $everything = Activity::query()->count();

    // Opening on "only what is strong" must say it is not showing everything.
    Livewire::test('admin.audit.index')->assertSee('de '.$everything.' movimientos');
});
