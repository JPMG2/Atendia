<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Menu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(RefreshDatabase::class)
    ->in('Feature', 'Browser', 'Eval');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', fn () => $this->toBe(1));

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something(): void
{
    // ..
}

/*
|--------------------------------------------------------------------------
| The two panels
|--------------------------------------------------------------------------
|
| Three guardians used to walk `panel = 'client'` and nothing walked the
| admin, so every defect they closed on the client panel could be repeated
| there untouched. They are one guardian each now, swept over both panels:
| consolidating beats a sibling test per panel, which drifts apart the day
| one of the two is edited (reglas-de-oro-enforcement.md).
|
*/

dataset('panels', [
    'client' => ['client'],
    'admin' => ['admin'],
]);

/**
 * Every route the menu of a panel points at. The menu is the source of the
 * list on purpose: a screen added tomorrow is swept without anybody
 * maintaining an array in a test.
 *
 * @return Collection<int, string>
 */
function panelRoutes(string $panel): Collection
{
    return Menu::query()
        ->where('panel', $panel)
        ->whereNotNull('route_name')
        ->pluck('route_name')
        ->unique()
        ->values();
}

/**
 * The user a panel answers to: an owner with her business for the client
 * panel, the platform's admin for the other. `RolesAndPermissionsSeeder`
 * has to have run — the role carries the area permission.
 *
 * `$withBusiness = false` is the user whose business is not born yet, the
 * one the fresh-render guardian needs.
 */
function signInOnPanel(string $panel, bool $withBusiness = true): User
{
    if ($panel === 'admin') {
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole('admin');

        return tap($admin->refresh(), fn (User $user) => test()->actingAs($user));
    }

    $owner = User::factory()->create([
        'business_id' => null,
        'email_verified_at' => now(),
    ]);

    if ($withBusiness) {
        $owner->business()->associate(Business::factory()->create())->save();
    }

    return tap($owner->refresh(), fn (User $user) => test()->actingAs($user));
}

/**
 * Which of the two swapped views of a catalog master is on screen. They are
 * toggled with `x-show`, so both live in the DOM and only the computed display
 * tells them apart. Shared by every catalog browser test.
 */
function visibleCatalogView(object $page): string
{
    $list = $page->script('getComputedStyle(document.querySelectorAll(".catalog-view")[0]).display');
    $form = $page->script('getComputedStyle(document.querySelectorAll(".catalog-view")[1]).display');

    return $form !== 'none' ? 'form' : ($list !== 'none' ? 'list' : 'none');
}

/** Config that the editor hands to the shared Alpine rail, e.g. `items` or `search`. */
function railConfig(string $html, string $key): array
{
    preg_match("/{$key}: JSON\.parse\('(.*?)'\)/", $html, $matches);

    return json_decode(json_decode('"'.$matches[1].'"'), true);
}
