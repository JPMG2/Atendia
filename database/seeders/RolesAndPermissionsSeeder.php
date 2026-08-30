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
        ];

        foreach ([...$areaPermissions, ...$catalogPermissions] as $permission) {
            Permission::findOrCreate($permission);
        }

        $admin = Role::findOrCreate('admin');
        $client = Role::findOrCreate('client');

        // The client reaches its own panel. The admin also passes through
        // Gate::before, but gets the permissions explicitly so the middleware
        // lets it through without leaning on super-admin alone.
        $client->givePermissionTo('access-client-app');
        $admin->givePermissionTo(['access-admin-panel', 'access-client-app', ...$catalogPermissions]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
