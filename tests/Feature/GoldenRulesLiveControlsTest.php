<?php

declare(strict_types=1);

use App\Models\Menu;
use Database\Seeders\MenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ControlScanner;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Golden rule: a control the panel draws does something
|--------------------------------------------------------------------------
| Born 2026-10-01, after the audit caught this by hand four times: the topbar
| chip that told every client WhatsApp was connected, the bell's red dot lit
| since June, the search box that is only CSS, and an AI banner printing
| "Optimizar con IA" on two screens with no method behind it. Nothing told the
| build, so each one lived for months.
|
| Fix when red: wire the control, or stop drawing it. The allowlist of
| ControlScanner holds the ones that report a state instead of offering one and
| it does not grow — a silent button is fixed, not registered.
|
| The menu is the same rule on another surface, so it lives here and not in a
| file of its own. It needs the database, not the scanner.
*/

test('no view draws an action control that runs nothing', function (): void {
    $silent = [];

    foreach (ControlScanner::views() as $view) {
        foreach (ControlScanner::silentControlsIn($view, (string) file_get_contents(base_path($view))) as $control) {
            $silent[] = $view.':'.$control['line'].'  '.$control['tag'];
        }
    }

    expect($silent)->toBe([]);
});

test('the scanner reads a control that is only wired after a directive', function (): void {
    // Guards the scanner itself: the `>` inside @class() used to end the tag
    // early, which made every such control look silent and the list useless.
    $wired = <<<'BLADE'
        <button type="button" @class(['ag-day', 'is-current' => $day->isToday()]) wire:click="pick">x</button>
        BLADE;

    expect(ControlScanner::silentControlsIn('resources/views/probe.blade.php', $wired))->toBe([]);
});

test('the scanner names a control with nothing behind it', function (): void {
    $silent = ControlScanner::silentControlsIn(
        'resources/views/probe.blade.php',
        '<x-ui.button variant="secondary" size="sm" icon="sparkles">Optimizar con IA</x-ui.button>',
    );

    expect($silent)->toHaveCount(1)
        ->and($silent[0]['line'])->toBe(1);
});

/*
| An item that leads nowhere promises more than a mute button: it names a whole
| screen. `menu.admin_users` was drawn with no route behind it. A parent without
| a route is legal — it opens its children; a leaf with nothing is not.
*/
test('no menu item is drawn without somewhere to go', function (): void {
    $this->seed(MenuSeeder::class);

    $items = Menu::query()->get(['id', 'parent_id', 'panel', 'label_key', 'route_name']);

    expect($items)->not->toBeEmpty();

    $parents = $items->whereNotNull('parent_id')->pluck('parent_id')->unique();

    $mute = $items
        ->filter(fn (Menu $item): bool => $item->route_name === null && ! $parents->contains($item->id))
        ->map(fn (Menu $item): string => $item->panel.' · '.$item->label_key.' has no route and no children')
        ->values()
        ->all();

    expect($mute)->toBe([]);
});
