<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The panel seen by somebody who is NOT the owner
|--------------------------------------------------------------------------
| The point of A5 in one picture: the same panel, with only the doors this
| person's job needs. A menu offering a screen that answers 403 is worse
| than one that never offered it.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
});

test('a support person sees a panel cut to their job', function (): void {
    $support = User::factory()->create(['name' => 'Rocío Paz', 'email_verified_at' => now()]);
    $support->assignRole('support');
    $this->actingAs($support->refresh());

    $page = visit(route('admin.support'))->resize(1280, 900);

    $page->assertNoJavaScriptErrors()
        ->assertSee(__('menu.admin_support'))
        ->assertSee(__('menu.admin_businesses'))
        // The areas this job has no key for are not even offered.
        ->assertDontSee(__('menu.admin_catalogs'))
        ->assertDontSee(__('menu.admin_settings'))
        ->assertDontSee(__('menu.admin_platform'))
        ->screenshot(filename: 'admin-panel-as-support');
});

test('the owner sees the whole panel, for comparison', function (): void {
    $admin = User::factory()->create(['name' => 'Juan', 'email_verified_at' => now()]);
    $admin->assignRole('admin');
    $this->actingAs($admin->refresh());

    $page = visit(route('admin.support'))->resize(1280, 900);

    // The group is collapsed until clicked, so its children are in the page
    // without being on screen: what proves the owner keeps them is the group.
    $page->assertNoJavaScriptErrors()
        ->assertSee(__('menu.admin_platform'))
        ->click(__('menu.admin_platform'))
        ->assertSee(__('menu.admin_catalogs'))
        ->assertSee(__('menu.admin_settings'))
        ->screenshot(filename: 'admin-panel-as-owner');
});
