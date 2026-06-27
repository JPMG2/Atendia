<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Promueve al usuario de ADMIN_EMAIL al rol "admin" y degrada a cualquier
     * otro admin a "client" (solo el email configurado queda admin). Idempotente.
     * Requiere que el rol admin exista (RolesAndPermissionsSeeder primero) y que
     * el usuario ya esté registrado.
     */
    public function run(): void
    {
        $email = config('atendia.admin_email');

        if (blank($email)) {
            $this->command?->warn('ADMIN_EMAIL no configurado; se omite la asignación de admin.');

            return;
        }

        $admin = User::where('email', $email)->first();

        if ($admin === null) {
            $this->command?->warn("No existe usuario con email [{$email}]; registralo y re-corré el seeder.");

            return;
        }

        // Degradar a cualquier admin previo distinto del configurado.
        User::role('admin')
            ->where('id', '!=', $admin->id)
            ->get()
            ->each(fn (User $user) => $user->syncRoles(['client']));

        $admin->syncRoles(['admin']);

        $this->command?->info("Usuario [{$email}] promovido a admin.");
    }
}
