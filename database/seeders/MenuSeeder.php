<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    /**
     * Seed the temporary dashboard navigation.
     *
     * Skeleton menu: options will change as the product grows. Includes a
     * nested branch (Productos → Categorías → Activas) to exercise the
     * recursive, arbitrary-depth tree.
     */
    public function run(): void
    {
        // Idempotent: clear the seeded menu first so re-running gives a clean tree
        // (children cascade on delete). Safe — menus is seed data, not user data.
        Menu::query()->delete();

        // Main navigation group (iconos según Claude Design).
        Menu::create(['label_key' => 'menu.home', 'icon' => 'layout-dashboard', 'route_name' => 'dashboard', 'sort_order' => 1]);
        Menu::create(['label_key' => 'menu.conversations', 'icon' => 'message-circle', 'badge' => '3', 'sort_order' => 2]);
        Menu::create(['label_key' => 'menu.agenda', 'icon' => 'calendar', 'badge' => '12', 'sort_order' => 3]);

        $products = Menu::create(['label_key' => 'menu.products', 'icon' => 'package', 'sort_order' => 4]);
        Menu::create(['parent_id' => $products->id, 'label_key' => 'menu.products_catalog', 'icon' => 'store', 'sort_order' => 1]);

        $categories = Menu::create(['parent_id' => $products->id, 'label_key' => 'menu.products_categories', 'icon' => 'sliders-horizontal', 'sort_order' => 2]);
        Menu::create(['parent_id' => $categories->id, 'label_key' => 'menu.products_categories_active', 'icon' => 'check', 'sort_order' => 1]);

        Menu::create(['label_key' => 'menu.metrics', 'icon' => 'bar-chart-3', 'sort_order' => 5]);

        // Bottom navigation group.
        Menu::create(['label_key' => 'menu.settings', 'icon' => 'settings', 'route_name' => 'profile.edit', 'placement' => 'bottom', 'sort_order' => 1]);
        Menu::create(['label_key' => 'menu.help', 'icon' => 'life-buoy', 'placement' => 'bottom', 'sort_order' => 2]);
    }
}
