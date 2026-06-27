<?php

use App\Models\Menu;
use Database\Seeders\MenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a menu loads its children recursively to arbitrary depth', function (): void {
    $root = Menu::factory()->create();
    $child = Menu::factory()->childOf($root)->create();
    $grandchild = Menu::factory()->childOf($child)->create();

    $tree = Menu::query()->roots()->with('childrenRecursive')->get();

    expect($tree)->toHaveCount(1)
        ->and($tree->first()->childrenRecursive->first()->id)->toBe($child->id)
        ->and($tree->first()->childrenRecursive->first()->childrenRecursive->first()->id)->toBe($grandchild->id);
});

test('the tree returns active roots ordered by sort_order with nested children', function (): void {
    $second = Menu::factory()->create(['sort_order' => 2]);
    $first = Menu::factory()->create(['sort_order' => 1]);
    Menu::factory()->childOf($first)->create();

    $tree = Menu::tree();

    expect($tree->pluck('id')->all())->toBe([$first->id, $second->id])
        ->and($tree->first()->childrenRecursive)->toHaveCount(1);
});

test('the tree excludes inactive items', function (): void {
    Menu::factory()->create(['label_key' => 'menu.home']);
    Menu::factory()->inactive()->create(['label_key' => 'menu.help']);

    expect(Menu::tree())->toHaveCount(1);
});

test('the label accessor resolves the i18n key', function (): void {
    app()->setLocale('es');
    $menu = Menu::factory()->create(['label_key' => 'menu.home']);

    expect($menu->label)->toBe('Inicio');
});

test('the url accessor resolves a route name or null for group items', function (): void {
    $linked = Menu::factory()->create(['route_name' => 'dashboard']);
    $group = Menu::factory()->create(['route_name' => null]);

    expect($linked->url)->toBe(route('dashboard'))
        ->and($group->url)->toBeNull();
});

test('the tree reflects newly added menu items', function (): void {
    Menu::factory()->create();
    expect(Menu::tree())->toHaveCount(1);

    Menu::factory()->create();

    expect(Menu::tree())->toHaveCount(2);
});

test('the seeder builds the temporary dashboard tree with nesting', function (): void {
    $this->seed(MenuSeeder::class);

    $tree = Menu::tree();
    $products = $tree->firstWhere('label_key', 'menu.products');

    expect($tree->where('placement', 'main'))->toHaveCount(5)
        ->and($tree->where('placement', 'bottom'))->toHaveCount(2)
        ->and($products->childrenRecursive)->toHaveCount(2)
        ->and($products->childrenRecursive->firstWhere('label_key', 'menu.products_categories')->childrenRecursive)->toHaveCount(1);
});
