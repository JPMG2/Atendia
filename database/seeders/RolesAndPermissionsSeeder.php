<?php

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

        foreach (['access-admin-panel', 'access-client-app'] as $permission) {
            Permission::findOrCreate($permission);
        }

        $admin = Role::findOrCreate('admin');
        $client = Role::findOrCreate('client');

        // El cliente entra a su panel. El admin además pasa por Gate::before
        // (super-admin), pero le damos ambos permisos explícitos para que el
        // middleware de permiso lo deje pasar sin depender solo del gate.
        $client->givePermissionTo('access-client-app');
        $admin->givePermissionTo(['access-admin-panel', 'access-client-app']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
