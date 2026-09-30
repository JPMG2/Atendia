<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Menu;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);

    $business = Business::factory()->create([
        'name' => 'Centro Odontológico Integral del Sur',
        'appointments_enabled' => true,
    ]);

    $owner = User::factory()->create(['name' => 'María Fernanda González Rodríguez']);
    $owner->business()->associate($business)->save();

    $this->actingAs($owner);
});

/**
 * Mandates 1 and 2 of the design system, measured instead of eyeballed: a phone
 * that scrolls sideways hides half of whatever the row was saying, and nobody
 * goes looking for it. The list comes from the menu, so a screen added tomorrow
 * is swept without anyone maintaining an array here. The dark shot of each one
 * is the evidence a human still has to look at.
 */
test('no client screen scrolls sideways on a phone, in the dark', function (): void {
    $routes = Menu::query()
        ->where('panel', 'client')
        ->whereNotNull('route_name')
        ->pluck('route_name')
        ->unique()
        ->values();

    expect($routes)->not->toBeEmpty();

    $wide = [];

    foreach ($routes as $name) {
        $page = visit(route($name))->resize(390, 844);

        // The toggle is per page: a fresh context does not carry localStorage.
        $page->click('@theme-toggle')
            ->assertNoJavaScriptErrors()
            ->screenshot(filename: 'phone-dark-'.str_replace('.', '-', $name));

        $overflow = $page->script('document.documentElement.scrollWidth - window.innerWidth');

        if ((int) $overflow > 0) {
            $wide[] = $name.' overflows by '.$overflow.'px';
        }
    }

    expect($wide)->toBe([]);
});
