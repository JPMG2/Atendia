<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Promotes the ADMIN_EMAIL user to "admin" and demotes any other admin to
     * "client", so only the configured address stays admin. Idempotent. Needs the
     * role to exist and the user to be registered already.
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

        // Demote any previous admin other than the configured one.
        User::role('admin')
            ->where('id', '!=', $admin->id)
            ->get()
            ->each(fn (User $user) => $user->syncRoles(['client']));

        $admin->syncRoles(['admin']);

        $this->command?->info("Usuario [{$email}] promovido a admin.");
    }
}
