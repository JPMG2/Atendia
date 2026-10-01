<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Menu;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Golden rule: every client screen names itself in the browser tab
|--------------------------------------------------------------------------
| The tab title is copy (`formularios.md` §4) and the written rule only
| fenced HOW it travels — `render()` with `__()`, never a PHP attribute.
| Nothing checked that it travels at all, so Inicio, Mi negocio, Servicios
| and Productos fell back to the bare app name and four open tabs of the
| panel read the same word while their eleven siblings named themselves.
|
| Capa B only on purpose: the title is a property of the RENDER, and which
| component serves a menu item is not written in the component's file, so
| no PostToolUse hook could read it there. The menu is the source of the
| list, so a screen added tomorrow is covered with nobody editing an array.
*/

test('every client screen names itself in the browser tab', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);

    $user = User::factory()->create();
    $user->business()->associate(Business::factory()->create())->save();

    $this->actingAs($user->refresh());

    $routes = Menu::query()
        ->where('panel', 'client')
        ->whereNotNull('route_name')
        ->pluck('route_name')
        ->unique()
        ->values();

    expect($routes)->not->toBeEmpty();

    $nameless = [];

    foreach ($routes as $name) {
        $response = $this->get(route($name));

        if ($response->getStatusCode() !== 200) {
            continue;
        }

        preg_match('#<title[^>]*>(.*?)</title>#s', (string) $response->getContent(), $found);
        $title = trim($found[1] ?? '');

        // The layout's fallback is the app name: a tab that says only that is
        // the screen staying quiet, not the screen having a short name.
        if ($title === '' || $title === config('app.name')) {
            $nameless[] = $name.' leaves the tab saying '.($title === '' ? 'nothing' : $title);
        }

        // "Gana con AtendIa · AtendIa": the screen's own name may carry the brand.
        if (substr_count($title, (string) config('app.name')) > 1) {
            $nameless[] = $name.' says the brand twice: '.$title;
        }
    }

    expect($nameless)->toBe([]);
});
