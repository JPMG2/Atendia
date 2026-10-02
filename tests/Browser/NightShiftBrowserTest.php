<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Opening hours, on the screen
|--------------------------------------------------------------------------
| The overlap message has to land beside the shift she must move, and the
| night shift has to survive a real save. Neither is provable server-side:
| the error is wired to the field by name, in the browser.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);

    $this->business = Business::factory()->create([
        'name' => 'Bar La Esquina',
        'timezone' => 'America/Argentina/Buenos_Aires',
    ]);
    $user = User::factory()->create(['name' => 'Lucía Pérez']);
    $user->business()->associate($this->business)->save();
    $this->actingAs($user);
});

test('two shifts that step on each other are refused beside the one to move', function (): void {
    $page = visit(route('my-business.horarios'))->resize(1280, 1000);

    $page->assertNoJavaScriptErrors()
        ->assertSee(__('client.business.hours.overnight_hint'))
        ->click('[wire\\:key="day-1"] .switch-track')
        ->waitForText(__('client.business.hours.add_shift'));

    // Monday 09:00-18:00, then a second shift that walks over it.
    $page->fill('#bp-h-1-0-opens', '09:00')
        ->fill('#bp-h-1-0-closes', '18:00')
        ->click('text='.__('client.business.hours.add_shift'))
        ->fill('#bp-h-1-1-opens', '14:00')
        ->fill('#bp-h-1-1-closes', '20:00')
        ->click('[wire\\:click="save"]')
        ->waitForText('se pisa');

    $page->assertNoJavaScriptErrors()
        ->screenshot(filename: 'hours-overlap');

    expect($this->business->hours()->count())->toBe(0);
});

test('a bar that closes at 02:00 can save its own hours', function (): void {
    $page = visit(route('my-business.horarios'))->resize(1280, 1000);

    $page->click('[wire\\:key="day-1"] .switch-track')
        ->waitForText(__('client.business.hours.add_shift'))
        ->fill('#bp-h-1-0-opens', '22:00')
        ->fill('#bp-h-1-0-closes', '02:00')
        ->click('[wire\\:click="save"]')
        ->waitForText(__('client.business.hours.overnight_hint'));

    $page->assertNoJavaScriptErrors()
        ->assertDontSee('se pisa')
        ->screenshot(filename: 'hours-night-shift');

    expect($this->business->hours()->sole()->runsPastMidnight())->toBeTrue();
});
