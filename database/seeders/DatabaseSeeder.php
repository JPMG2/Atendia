<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $this->call(RolesAndPermissionsSeeder::class);
        $this->call(PlanSeeder::class);

        // UserFactory assigns the "client" role by default, roles being seeded.
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call(MenuSeeder::class);
        $this->call(HelpArticleSeeder::class);
        // The hero has no fallback copy any more: with no rows it shows no
        // examples at all, so a fresh database has to start with them.
        $this->call(DemoTagSeeder::class);
        $this->call(AdminUserSeeder::class);
    }
}
