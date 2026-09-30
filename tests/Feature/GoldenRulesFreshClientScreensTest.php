<?php

declare(strict_types=1);

use App\Models\Menu;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Golden rule: a client whose business is not born yet still gets a screen
|--------------------------------------------------------------------------
| `Client::for()` leaves every piece null until the business exists, so a
| screen that reads its piece bare answers a 500 to the very user who has
| the most to lose: the one who just registered. Agenda ("day() on null")
| and Equipo ("members on null") shipped like that and the suite never
| noticed — every test seeded a business first.
|
| Capa B only on purpose: the break is a runtime property of the render,
| not a pattern in a file a PostToolUse hook could read. The menu is the
| source of the list, so a screen added tomorrow is covered without anyone
| maintaining an array here.
*/

test('every client screen renders for a user whose business is not born yet', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);

    $this->actingAs(User::factory()->create(['business_id' => null, 'email_verified_at' => now()])->refresh());

    $routes = Menu::query()
        ->where('panel', 'client')
        ->whereNotNull('route_name')
        ->pluck('route_name')
        ->unique()
        ->values();

    expect($routes)->not->toBeEmpty();

    $broken = [];

    foreach ($routes as $name) {
        $status = $this->get(route($name))->getStatusCode();

        // A redirect is a fine answer (the onboarding gate); a 5xx is not.
        if ($status >= 500) {
            $broken[] = $name.' answered '.$status;
        }
    }

    expect($broken)->toBe([]);
});
