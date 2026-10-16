<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The sidebar never stops halfway down a long page
|--------------------------------------------------------------------------
| The report was a dark sidebar whose background ended mid-page. The
| measurement is geometric and theme-independent: scrolled to the bottom of a
| page three screens tall, the sidebar still starts at the top of the viewport,
| fills it exactly, and paints its own background.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
});

/** Whether the sidebar covers the whole viewport right now and has a background of its own. */
function sidebarFillsTheViewport(): string
{
    return "(() => { const s = document.querySelector('.sidebar'); const r = s.getBoundingClientRect(); return Math.round(r.top) === 0 && Math.round(r.height) === window.innerHeight && getComputedStyle(s).backgroundColor !== 'rgba(0, 0, 0, 0)'; })()";
}

test('the sidebar still fills the viewport at the bottom of a long page', function (bool $dark): void {
    $user = User::factory()->create();
    $user->business()->associate(Business::factory()->create())->save();
    $this->actingAs($user);

    $page = visit('/dashboard')->resize(1280, 800);

    if ($dark) {
        $page->script('document.documentElement.classList.add("dark")');
    }

    // Three screens of extra height INSIDE the content, as a long page has it, then all the way down.
    $page->script('document.querySelector(".app-content").insertAdjacentHTML("beforeend", "<div style=\"height:2400px\"></div>")');
    $page->script('window.scrollTo(0, document.documentElement.scrollHeight)');

    $page->wait(0.5)
        ->assertNoJavaScriptErrors()
        ->assertScript(sidebarFillsTheViewport(), true)
        ->screenshot(filename: 'sidebar-sticky-bottom-'.($dark ? 'dark' : 'light'));
})->with([
    'light' => [false],
    'dark' => [true],
]);
