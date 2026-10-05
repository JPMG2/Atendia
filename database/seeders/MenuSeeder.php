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
        // "Mi negocio" opens the whole profile; its children deep-link one
        // section each (LinkedIn-style: update just the piece you came for).
        // Labels reuse the section titles so menu and screen never diverge.
        $myBusiness = Menu::create(['permission' => 'manage-business', 'label_key' => 'menu.my_business', 'icon' => 'store', 'route_name' => 'my-business', 'sort_order' => 2]);
        $profileSections = [
            ['client.business.identity.title', 'sparkles', 'my-business.identidad'],
            ['client.business.location.title', 'map-pin', 'my-business.ubicacion'],
            ['client.business.hours.title', 'clock', 'my-business.horarios'],
            ['client.business.appointments.title', 'calendar-check', 'my-business.turnos'],
            ['client.business.contact.title', 'at-sign', 'my-business.contacto'],
            ['client.business.social.title', 'share-2', 'my-business.redes'],
            ['client.business.billing.title', 'receipt', 'my-business.facturacion'],
        ];
        foreach ($profileSections as $order => [$labelKey, $icon, $routeName]) {
            Menu::create(['parent_id' => $myBusiness->id, 'label_key' => $labelKey, 'icon' => $icon, 'route_name' => $routeName, 'sort_order' => $order + 1]);
        }
        // Last child of the business, not a top item (her call, 2026-09-27): the
        // people and departments are the owner's setup, like Shopify's "Users".
        Menu::create(['parent_id' => $myBusiness->id, 'label_key' => 'menu.team', 'icon' => 'users-round', 'route_name' => 'team', 'sort_order' => count($profileSections) + 1]);
        // Grouped under the Catalog parent by the owner's call (2026-09-14),
        // the Fresha pattern: a submenu whose children are both REAL screens.
        // Badges mirror the mock counts until the real tables land.
        $catalog = Menu::create(['permission' => 'manage-business', 'label_key' => 'menu.catalog', 'icon' => 'layers', 'sort_order' => 3]);
        // No seeded badges: the catalog counts are overlaid LIVE per tenant
        // by the navigation (a static number that lies costs trust).
        Menu::create(['parent_id' => $catalog->id, 'label_key' => 'menu.services', 'icon' => 'briefcase', 'route_name' => 'my-services', 'sort_order' => 1]);
        Menu::create(['parent_id' => $catalog->id, 'label_key' => 'menu.products', 'icon' => 'package', 'route_name' => 'my-products', 'sort_order' => 2]);
        // Setup-first order (owner's call, 2026-09-17): configure, then talk;
        // revisit post go-live. A parent (her call, 2026-09-20): knowledge and
        // behaviour are different rooms, and more children will grow here.
        $assistant = Menu::create(['permission' => 'manage-business', 'label_key' => 'menu.assistant', 'icon' => 'bot', 'sort_order' => 4]);
        Menu::create(['parent_id' => $assistant->id, 'label_key' => 'menu.assistant_knowledge', 'icon' => 'sparkles', 'route_name' => 'assistant', 'sort_order' => 1]);
        Menu::create(['parent_id' => $assistant->id, 'label_key' => 'menu.assistant_settings', 'icon' => 'settings', 'route_name' => 'assistant.settings', 'sort_order' => 2]);
        Menu::create(['label_key' => 'menu.conversations', 'icon' => 'message-circle', 'route_name' => 'conversations', 'sort_order' => 5]);
        // Beside the inbox on purpose: the flow and the asset it leaves behind.
        Menu::create(['label_key' => 'menu.customers', 'icon' => 'users', 'route_name' => 'customers', 'sort_order' => 6]);
        // Only a business that gives slots sees it: the navigation drops the
        // item when the agenda is off, so a bakery never reads "Agenda".
        Menu::create(['permission' => 'manage-business', 'label_key' => 'menu.agenda', 'icon' => 'calendar-check', 'route_name' => 'agenda', 'sort_order' => 7]);
        // Reading screens ride together: statistics right after the inbox.
        Menu::create(['permission' => 'manage-business', 'label_key' => 'menu.statistics', 'icon' => 'bar-chart-3', 'route_name' => 'statistics', 'sort_order' => 8]);
        Menu::create(['permission' => 'manage-business', 'label_key' => 'menu.whatsapp', 'icon' => 'whatsapp', 'route_name' => 'whatsapp', 'sort_order' => 9]);
        // A parent like "Mi negocio" (her call, 2026-09-23): the plan and what
        // it costs live together, the Tiendanube "Planes y pagos" pattern.
        $planPayments = Menu::create(['permission' => 'manage-business', 'label_key' => 'menu.plan_payments', 'icon' => 'gem', 'sort_order' => 10]);
        Menu::create(['parent_id' => $planPayments->id, 'label_key' => 'menu.plan', 'icon' => 'gem', 'route_name' => 'my-plan', 'sort_order' => 1]);
        Menu::create(['parent_id' => $planPayments->id, 'label_key' => 'menu.my_payments', 'icon' => 'receipt', 'route_name' => 'my-payments', 'sort_order' => 2]);
        Menu::create(['permission' => 'manage-business', 'label_key' => 'menu.referrals', 'icon' => 'gift', 'route_name' => 'referrals', 'sort_order' => 11]);

        // Bottom navigation group.
        Menu::create(['label_key' => 'menu.settings', 'icon' => 'settings', 'route_name' => 'settings', 'placement' => 'bottom', 'sort_order' => 1]);
        Menu::create(['label_key' => 'menu.help', 'icon' => 'life-buoy', 'route_name' => 'help', 'placement' => 'bottom', 'sort_order' => 2]);

        /*
         * ADMIN panel, as a tree (2026-10-03). Nine loose items became six
         * groups: she scans the group she is working IN, not a wall of names.
         * Grouped by what she does, labelled with nouns — and every pending
         * screen already has its branch, so the next one does not reshuffle
         * this again (pendientes-admin.md §E10).
         */
        Menu::create(['panel' => 'admin', 'label_key' => 'menu.admin_home', 'icon' => 'layout-dashboard', 'route_name' => 'admin.dashboard', 'sort_order' => 1]);

        // Negocios: who she serves. Incumplimientos (E4) and Radar (A10) land here.
        $businesses = Menu::create(['panel' => 'admin', 'label_key' => 'menu.admin_businesses', 'icon' => 'briefcase', 'sort_order' => 2]);
        Menu::create(['parent_id' => $businesses->id, 'panel' => 'admin', 'label_key' => 'menu.admin_all_businesses', 'icon' => 'store', 'route_name' => 'admin.businesses', 'sort_order' => 1]);
        Menu::create(['parent_id' => $businesses->id, 'panel' => 'admin', 'label_key' => 'menu.admin_adoption', 'icon' => 'signal', 'route_name' => 'admin.adoption', 'sort_order' => 2]);

        // Cobros: the money. Planes (E7) and Consumo de IA (A3) land here.
        $billing = Menu::create(['panel' => 'admin', 'label_key' => 'menu.admin_billing', 'icon' => 'credit-card', 'sort_order' => 3]);
        Menu::create(['parent_id' => $billing->id, 'panel' => 'admin', 'label_key' => 'menu.admin_payments', 'icon' => 'receipt', 'route_name' => 'admin.payments', 'sort_order' => 1]);
        Menu::create(['parent_id' => $billing->id, 'panel' => 'admin', 'label_key' => 'menu.admin_ai', 'icon' => 'bot', 'route_name' => 'admin.ai', 'sort_order' => 2]);
        Menu::create(['parent_id' => $billing->id, 'panel' => 'admin', 'label_key' => 'menu.admin_ai_usage', 'icon' => 'bar-chart-3', 'route_name' => 'admin.ai-usage', 'sort_order' => 3]);

        // Approving a testimonial IS moderation, so it belongs inside this
        // branch and not beside it. The AI ratings screen (A9) lands here too.
        $trust = Menu::create(['panel' => 'admin', 'label_key' => 'menu.admin_trust', 'icon' => 'shield-check', 'sort_order' => 4]);
        Menu::create(['parent_id' => $trust->id, 'panel' => 'admin', 'label_key' => 'menu.admin_content', 'icon' => 'eye', 'route_name' => 'admin.moderation', 'sort_order' => 1]);
        Menu::create(['parent_id' => $trust->id, 'panel' => 'admin', 'label_key' => 'menu.admin_testimonials', 'icon' => 'star', 'route_name' => 'admin.testimonials', 'sort_order' => 2]);

        Menu::create(['panel' => 'admin', 'label_key' => 'menu.admin_support', 'icon' => 'life-buoy', 'route_name' => 'admin.support', 'sort_order' => 5]);

        // How the product is set up, not the daily work. Platform settings
        // (A2), the hero tags (A8) and access control (A4, A5) land here.
        $platform = Menu::create(['panel' => 'admin', 'label_key' => 'menu.admin_platform', 'icon' => 'settings', 'sort_order' => 6]);
        Menu::create(['parent_id' => $platform->id, 'panel' => 'admin', 'label_key' => 'menu.admin_company', 'icon' => 'building-2', 'route_name' => 'admin.company', 'sort_order' => 1]);
        Menu::create(['parent_id' => $platform->id, 'panel' => 'admin', 'label_key' => 'menu.admin_catalogs', 'icon' => 'library', 'route_name' => 'admin.catalogs', 'sort_order' => 2]);
        Menu::create(['parent_id' => $platform->id, 'panel' => 'admin', 'label_key' => 'menu.admin_integrations', 'icon' => 'workflow', 'route_name' => 'admin.integrations', 'sort_order' => 3]);
        Menu::create(['parent_id' => $platform->id, 'panel' => 'admin', 'label_key' => 'menu.admin_logs', 'icon' => 'scroll-text', 'route_name' => 'admin.logs', 'sort_order' => 4]);
    }
}
