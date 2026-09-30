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
 * The two widths the design mandate names. The phone is the drawer layout; 900px
 * is the tightest the DESKTOP layout ever gets — the 264px sidebar still holds
 * its width there and the work area lives on what is left, which is why it needs
 * its own measurement instead of being assumed fine because the phone passed.
 */
dataset('client viewports', [
    'phone' => ['phone', 390, 844],
    'tablet' => ['tablet', 900, 1200],
]);

/**
 * Mandates 1 and 2 of the design system, measured instead of eyeballed: a screen
 * that scrolls sideways hides half of whatever the row was saying, and nobody
 * goes looking for it. The list comes from the menu, so a screen added tomorrow
 * is swept without anyone maintaining an array here. The dark shot of each one
 * is the evidence a human still has to look at.
 */
test('no client screen scrolls sideways on a small screen, in the dark', function (string $label, int $width, int $height): void {
    $routes = Menu::query()
        ->where('panel', 'client')
        ->whereNotNull('route_name')
        ->pluck('route_name')
        ->unique()
        ->values();

    expect($routes)->not->toBeEmpty();

    $wide = [];

    foreach ($routes as $name) {
        $page = visit(route($name))->resize($width, $height);

        // The toggle is per page: a fresh context does not carry localStorage.
        $page->click('@theme-toggle')
            ->assertNoJavaScriptErrors()
            ->screenshot(filename: $label.'-dark-'.str_replace('.', '-', $name));

        $overflow = $page->script('document.documentElement.scrollWidth - window.innerWidth');

        if ((int) $overflow > 0) {
            $wide[] = $name.' overflows by '.$overflow.'px';
        }
    }

    expect($wide)->toBe([]);
})->with('client viewports');
