<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    /**
     * Seeds the temporary dashboard navigation.
     *
     * A skeleton: the options will change as the product grows. It includes a
     * nested branch to exercise the recursive, arbitrary-depth tree.
     */
    public function run(): void
    {
        // Idempotent: clear the seeded menu first so re-running gives a clean tree
        // (children cascade on delete). Safe — menus is seed data, not user data.
        Menu::query()->delete();

        // Main navigation group.
        Menu::create(['label_key' => 'menu.home', 'icon' => 'layout-dashboard', 'route_name' => 'dashboard', 'sort_order' => 1]);
        Menu::create(['label_key' => 'menu.conversations', 'icon' => 'message-circle', 'badge' => '3', 'sort_order' => 2]);
        Menu::create(['label_key' => 'menu.agenda', 'icon' => 'calendar', 'badge' => '12', 'sort_order' => 3]);

        $products = Menu::create(['label_key' => 'menu.products', 'icon' => 'package', 'sort_order' => 4]);
        Menu::create(['parent_id' => $products->id, 'label_key' => 'menu.products_catalog', 'icon' => 'store', 'sort_order' => 1]);

        $categories = Menu::create(['parent_id' => $products->id, 'label_key' => 'menu.products_categories', 'icon' => 'sliders-horizontal', 'sort_order' => 2]);
        Menu::create(['parent_id' => $categories->id, 'label_key' => 'menu.products_categories_active', 'icon' => 'check', 'sort_order' => 1]);

        Menu::create(['label_key' => 'menu.metrics', 'icon' => 'bar-chart-3', 'sort_order' => 5]);

        // Bottom navigation group. Client items default to the 'client' panel.
        Menu::create(['label_key' => 'menu.settings', 'icon' => 'settings', 'route_name' => 'profile.edit', 'placement' => 'bottom', 'sort_order' => 1]);
        Menu::create(['label_key' => 'menu.help', 'icon' => 'life-buoy', 'placement' => 'bottom', 'sort_order' => 2]);

        // --- ADMIN panel (configuration) — skeleton; routes come later. ---
        Menu::create(['panel' => 'admin', 'label_key' => 'menu.admin_home', 'icon' => 'layout-dashboard', 'route_name' => 'admin.dashboard', 'sort_order' => 1]);
        Menu::create(['panel' => 'admin', 'label_key' => 'menu.admin_users', 'icon' => 'users', 'sort_order' => 2]);
        Menu::create(['panel' => 'admin', 'label_key' => 'menu.admin_catalogs', 'icon' => 'library', 'route_name' => 'admin.catalogs', 'sort_order' => 3]);
        // Company hangs off Configuration: it is AtendIa's own data, not an area.
        $settings = Menu::create(['panel' => 'admin', 'label_key' => 'menu.admin_settings', 'icon' => 'settings', 'sort_order' => 4]);
        Menu::create(['parent_id' => $settings->id, 'panel' => 'admin', 'label_key' => 'menu.admin_company', 'icon' => 'building-2', 'route_name' => 'admin.company', 'sort_order' => 1]);
        Menu::create(['parent_id' => $settings->id, 'panel' => 'admin', 'label_key' => 'menu.admin_integrations', 'icon' => 'workflow', 'route_name' => 'admin.integrations', 'sort_order' => 2]);
    }
}
