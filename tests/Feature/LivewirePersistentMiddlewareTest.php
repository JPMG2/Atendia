<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\CatalogFormSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Livewire\Drawer\Utils;
use Livewire\Mechanisms\HandleRequests\HandleRequests;
use Livewire\Mechanisms\PersistentMiddleware\PersistentMiddleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(CatalogFormSeeder::class);
});

/**
 * Post to the Livewire update endpoint reusing a snapshot harvested from a real
 * page render. This is the only way to exercise the persistent middleware:
 * `Livewire::test()` bypasses it on purpose (it is not the update route).
 *
 * @param  array<string, mixed>  $snapshot
 */
function postLivewireUpdate(array $snapshot): TestResponse
{
    // Livewire 4 obfuscates the endpoint (/livewire-<hash>/update), so it is
    // resolved through the mechanism instead of hardcoded.
    $uri = app(HandleRequests::class)->getUpdateUri();

    return test()->postJson($uri, [
        'components' => [[
            'snapshot' => json_encode($snapshot),
            'updates' => [],
            'calls' => [['path' => '', 'method' => '$refresh', 'params' => []]],
        ]],
    ], [
        // The endpoint is guarded by RequireLivewireHeaders; without it, 404.
        'X-Livewire' => 'true',
    ]);
}

test('spatie middleware is registered as persistent so it survives livewire updates', function (): void {
    // Livewire only re-applies a whitelist on POST /livewire/update. Without this
    // registration the whole `permission:` guard silently stops after page load.
    expect(app(PersistentMiddleware::class)->getPersistentMiddleware())
        ->toContain(PermissionMiddleware::class)
        ->toContain(RoleMiddleware::class)
        ->toContain(RoleOrPermissionMiddleware::class);
});

/**
 * Render the admin catalogs page as the given user and return the snapshot of the
 * first Livewire component on it — the same payload the browser would send back.
 *
 * @return array<string, mixed>
 */
function adminPageSnapshot(User $user): array
{
    $html = test()->actingAs($user)->get('/admin/catalogs')->assertOk()->getContent();

    $snapshot = Utils::extractAttributeDataFromHtml($html, 'wire:snapshot');

    expect($snapshot)->toBeArray()
        ->and($snapshot['memo']['path'])->toBe('admin/catalogs');

    return $snapshot;
}

// Control: proves the crafted request is well formed, so the 403 in the next test
// means "middleware rejected it" and not "malformed payload".
// Note both cases live in separate tests on purpose: PersistentMiddleware caches
// `middlewareAppliedFor` per route, and in tests every request shares one app
// instance, so a second update in the same test would skip the middleware.
test('a livewire update on an admin page succeeds while the permission is held', function (): void {
    $admin = User::factory()->create();
    $admin->syncRoles('admin');

    postLivewireUpdate(adminPageSnapshot($admin))->assertOk();
});

test('a livewire update on an admin page is rejected once the permission is revoked', function (): void {
    $admin = User::factory()->create();
    $admin->syncRoles('admin');

    $snapshot = adminPageSnapshot($admin);

    // Access is taken away mid-session: the user still holds a valid, signed snapshot.
    $admin->syncRoles([]);

    postLivewireUpdate($snapshot)->assertForbidden();
});
