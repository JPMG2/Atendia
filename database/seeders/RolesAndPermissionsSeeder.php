<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Roles and permissions of the two-panel architecture. AREA permissions for
     * now; the fine-grained ones arrive with their feature.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Area permissions.
        $areaPermissions = ['access-admin-panel', 'access-client-app'];

        // Fine-grained permissions per catalog master. They have to match
        // `permission_key` in CatalogFormSeeder.
        $catalogPermissions = [
            'catalog.country', 'catalog.province', 'catalog.region',
            'catalog.currency', 'catalog.tax-condition',
            'catalog.status', 'catalog.social-network',
            'catalog.business-sector', 'catalog.business-activity',
            'catalog.service-modality', 'catalog.service-attribute', 'catalog.service-type',
            'catalog.seasonal-window', 'catalog.demo-tag', 'catalog.support-reply', 'catalog.adoption-nudge',
        ];

        // One key per door of the admin panel. Until 2026-10-05 every route
        // asked only for the area key, so anyone let in saw the whole panel:
        // support could not work a ticket without reaching the settings.
        $adminPermissions = [
            'businesses.view', 'businesses.manage',
            'payments.view', 'payments.verify',
            'moderation.view', 'testimonials.moderate',
            'support.view',
            'ai.view', 'ai.manage',
            'adoption.view', 'incidents.view', 'contacts.view',
            'catalogs.manage', 'company.manage', 'integrations.view',
            'settings.manage', 'users.view', 'logs.view', 'roles.manage', 'audit.view',
        ];

        foreach ([...$areaPermissions, ...$catalogPermissions, ...$adminPermissions] as $permission) {
            Permission::findOrCreate($permission);
        }

        // The business setup (profile, catalog, assistant, plan, team) is the
        // owner's; an invited agent only works the inbox.
        Permission::findOrCreate('manage-business');

        // Who may give somebody else a key to the admin panel. It is its own
        // permission so the owner can hand the panel to a support person
        // without handing over the ability to create more of them.
        Permission::findOrCreate('manage-admin-users');

        // Taking somebody's second step away is the way to take over their account,
        // so it is the owner's alone and is not in any other role by default.
        Permission::findOrCreate('reset-two-factor');

        $admin = Role::findOrCreate('admin');
        $client = Role::findOrCreate('client');
        $agent = Role::findOrCreate('agent');

        // Staff: the panel WITHOUT super-admin, which stays seeder-only
        // because `admin` passes every gate. Least privilege, her own example:
        // works the claims, looks up the business, no catalogs and no settings.
        $support = Role::findOrCreate('support');
        $support->syncPermissions(['access-admin-panel', 'support.view', 'businesses.view']);

        // The client reaches its own panel. The admin also passes through
        // Gate::before, but gets the permissions explicitly so the middleware
        // lets it through without leaning on super-admin alone.
        $client->givePermissionTo(['access-client-app', 'manage-business']);
        $agent->givePermissionTo('access-client-app');
        $admin->givePermissionTo(['access-admin-panel', 'access-client-app', 'manage-business', 'manage-admin-users', 'reset-two-factor', ...$catalogPermissions, ...$adminPermissions]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
