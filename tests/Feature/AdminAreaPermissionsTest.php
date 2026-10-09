<?php

declare(strict_types=1);

use App\Models\Menu;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Golden rule: one key per door of the admin panel
|--------------------------------------------------------------------------
| Until 2026-10-05 every admin route asked only for `access-admin-panel`,
| so letting a support person in handed them the catalogs, the company and
| the platform settings too. The panel is meant to grow with people who do
| different jobs — claims, design, call centre — and that is only possible
| if each area has its own key and the menu offers only the doors that open.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
});

function staffWith(array $permissions): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('support');
    $user->syncPermissions([]);

    return tap($user->refresh(), fn (User $u) => test()->actingAs($u));
}

function supportPerson(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('support');

    return tap($user->refresh(), fn (User $u) => test()->actingAs($u));
}

test('support reaches its own work and nothing else', function (): void {
    supportPerson();

    // What the job needs.
    $this->get(route('admin.support'))->assertOk();
    $this->get(route('admin.businesses'))->assertOk();

    // What it does not. Her own words: raises claims, no catalogs, no config.
    $this->get(route('admin.catalogs'))->assertForbidden();
    $this->get(route('admin.settings'))->assertForbidden();
    $this->get(route('admin.company'))->assertForbidden();
    $this->get(route('admin.users'))->assertForbidden();
    $this->get(route('admin.payments'))->assertForbidden();
    $this->get(route('admin.ai'))->assertForbidden();
    $this->get(route('admin.logs'))->assertForbidden();
});

test('the owner still reaches every door', function (): void {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $this->actingAs($admin->refresh());

    foreach (['support', 'businesses', 'catalogs', 'settings', 'company', 'users', 'payments', 'ai', 'ai-usage', 'logs', 'moderation', 'adoption', 'testimonials'] as $name) {
        $this->get(route('admin.'.$name))->assertOk();
    }
});

test('the menu offers only the doors that open for whoever is looking', function (): void {
    $support = supportPerson();

    $labels = collect(Menu::tree('admin'))
        ->flatMap(fn (Menu $item): array => [$item->label_key, ...$item->childrenRecursive->pluck('label_key')->all()])
        ->all();

    expect($labels)->toContain('menu.admin_support')
        ->toContain('menu.admin_all_businesses')
        // Offering a door that answers 403 is worse than not offering it.
        ->not->toContain('menu.admin_catalogs')
        ->not->toContain('menu.admin_settings')
        ->not->toContain('menu.admin_users');
});

/*
| The lock lives on the ROUTE, never on the menu alone: hiding a link is UX,
| and a URL typed by hand has to be refused all the same.
*/
test('every admin screen declares a permission of its own', function (): void {
    // `admin.security` is each person's own page: it asks for nothing beyond the area
    // key, and it is the one screen left open once the plazo of the second step ran out.
    $exempt = ['admin.dashboard', 'admin.ws-demo', 'admin.security'];

    $naked = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_starts_with((string) $route->getName(), 'admin.'))
        ->reject(fn ($route): bool => in_array($route->getName(), $exempt, true))
        ->reject(fn ($route): bool => collect($route->gatherMiddleware())
            ->contains(fn ($m): bool => is_string($m) && str_starts_with($m, 'permission:') && $m !== 'permission:access-admin-panel'))
        ->map(fn ($route): string => (string) $route->getName())
        ->values()
        ->all();

    expect($naked)->toBe([]);
});
