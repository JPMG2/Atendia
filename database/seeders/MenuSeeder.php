<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    /**
     * Seeds the dashboard navigation for both panels.
     *
     * The client sections are the six blessed on 2026-09-06 (research pass:
     * Mercado Libre, Amazon, Meta, GBP + NN/g): see first, then feed the
     * assistant, then connect. Routes arrive with each screen.
     */
    public function run(): void
    {
        // Idempotent: clear the seeded menu first so re-running gives a clean tree
        // (children cascade on delete). Safe — menus is seed data, not user data.
        Menu::query()->delete();

        // Client panel, main group (items default to the 'client' panel).
        Menu::create(['label_key' => 'menu.home', 'icon' => 'layout-dashboard', 'route_name' => 'dashboard', 'sort_order' => 1]);
        Menu::create(['label_key' => 'menu.conversations', 'icon' => 'message-circle', 'sort_order' => 2]);
        Menu::create(['label_key' => 'menu.my_business', 'icon' => 'store', 'sort_order' => 3]);
        Menu::create(['label_key' => 'menu.services', 'icon' => 'briefcase', 'sort_order' => 4]);
        Menu::create(['label_key' => 'menu.products', 'icon' => 'package', 'sort_order' => 5]);
        Menu::create(['label_key' => 'menu.whatsapp', 'icon' => 'whatsapp', 'sort_order' => 6]);

        // Bottom navigation group.
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
        Menu::create(['parent_id' => $settings->id, 'panel' => 'admin', 'label_key' => 'menu.admin_logs', 'icon' => 'scroll-text', 'route_name' => 'admin.logs', 'sort_order' => 3]);
    }
}
