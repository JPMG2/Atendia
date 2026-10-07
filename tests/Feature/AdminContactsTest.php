<?php

declare(strict_types=1);

use App\Models\PlatformContact;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** An admin who may open the radar, with the menu the layout reads. */
function radarAdmin(): User
{
    test()->seed(RolesAndPermissionsSeeder::class);
    test()->seed(MenuSeeder::class);

    $admin = User::factory()->create();
    $admin->syncRoles('admin');

    return $admin;
}

test('the person who talks to more than one business comes first', function (): void {
    PlatformContact::factory()->create([
        'phone' => '5491100000001',
        'businesses_count' => 1,
        'last_activity_at' => now(),
    ]);
    $shared = PlatformContact::factory()->create([
        'phone' => '5491100000002',
        'businesses_count' => 3,
        'last_activity_at' => now()->subDays(5),
    ]);

    // Newest-first would bury it: the overlap is the only thing this table
    // knows that no business can see from its own panel.
    expect(PlatformContact::radar()->first()->id)->toBe($shared->id);
});

test('the reach counts people, the shared ones, their chats and their countries', function (): void {
    PlatformContact::factory()->create(['businesses_count' => 2, 'conversations_count' => 4, 'country_code' => 'AR']);
    PlatformContact::factory()->create(['businesses_count' => 1, 'conversations_count' => 1, 'country_code' => 'AR']);
    PlatformContact::factory()->create(['businesses_count' => 1, 'conversations_count' => 2, 'country_code' => 'VE']);

    expect(PlatformContact::reach())->toBe([
        'people' => 3,
        'shared' => 1,
        'conversations' => 7,
        'countries' => 2,
    ]);
});

test('the radar shows the people and tags the shared one', function (): void {
    PlatformContact::factory()->create([
        'phone' => '5491100000002',
        'name' => 'Marcela',
        'businesses_count' => 3,
        'conversations_count' => 9,
        'country_code' => 'AR',
    ]);

    $this->actingAs(radarAdmin())->get(route('admin.contacts'))
        ->assertOk()
        ->assertSee('5491100000002')
        ->assertSee('Marcela')
        ->assertSee(__('contacts.shared_tag', ['count' => 3]));
});

test('with nobody shared the screen says so instead of leaving a zero to read', function (): void {
    PlatformContact::factory()->create(['businesses_count' => 1]);

    $this->actingAs(radarAdmin())->get(route('admin.contacts'))
        ->assertOk()
        ->assertSee(__('contacts.none_shared'));
});

test('with nobody at all the radar explains what will appear there', function (): void {
    $this->actingAs(radarAdmin())->get(route('admin.contacts'))
        ->assertOk()
        ->assertSee(__('contacts.empty_title'))
        ->assertDontSee(__('contacts.table.phone'));
});

test('a client cannot reach the radar', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->actingAs(User::factory()->create())->get(route('admin.contacts'))->assertForbidden();
});
