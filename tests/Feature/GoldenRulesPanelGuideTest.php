<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Menu;
use Database\Seeders\MenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Golden rule: the owner's assistant knows every screen of her panel
|--------------------------------------------------------------------------
| "Pregúntale a AtendIa" explains a module from the texts of its screen
| (config atendia.owner_assistant.guide). Agenda shipped on 2026-09-28 with
| its menu item, its screen and its three customer skills — and nobody added
| it to the guide, so the assistant denied a screen that was in the menu.
| Capa B only on purpose: the break happens when a menu item is seeded, not
| in a file a PostToolUse hook could watch, and whoever seeds one runs Pest.
*/

test('every client menu item with a screen is in the owner panel guide', function (): void {
    $this->seed(MenuSeeder::class);

    $guide = (array) config('atendia.owner_assistant.guide');

    $missing = Menu::query()
        ->where('panel', 'client')
        ->whereNotNull('route_name')
        ->pluck('route_name')
        ->unique()
        // A deep link into a screen the guide already covers (the sections of
        // "Mi negocio") is that same screen, not a module of its own.
        ->reject(fn (string $route): bool => array_key_exists($route, $guide)
            || array_key_exists((string) strstr($route, '.', true), $guide))
        ->values()
        ->all();

    expect($missing)->toBe([]);
});

test('a gated guide entry names a real business column', function (): void {
    $columns = Schema::getColumnListing((new Business)->getTable());
    $unknown = [];

    foreach ((array) config('atendia.owner_assistant.guide') as $module => $texts) {
        if (isset($texts[2]) && ! in_array($texts[2], $columns, true)) {
            $unknown[] = $module.' gated on '.$texts[2];
        }
    }

    expect($unknown)->toBe([]);
});
