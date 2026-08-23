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
     * Roles y permisos de la arquitectura de paneles (admin / cliente).
     * Permisos de ÁREA por ahora; los finos se suman por feature.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Permisos de área.
        $areaPermissions = ['access-admin-panel', 'access-client-app'];

        // Permisos finos por maestro del panel de Catálogos (namespace catalog.*).
        // Deben coincidir con `permission_key` en CatalogFormSeeder.
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

        // El cliente entra a su panel. El admin además pasa por Gate::before
        // (super-admin), pero le damos los permisos explícitos para que el
        // middleware/gate lo deje pasar sin depender solo del super-admin.
        $client->givePermissionTo('access-client-app');
        $admin->givePermissionTo(['access-admin-panel', 'access-client-app', ...$catalogPermissions]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
