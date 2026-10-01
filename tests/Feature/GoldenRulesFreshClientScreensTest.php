<?php

declare(strict_types=1);

use App\Models\Menu;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The screen with its head cut off: everything the page head prints — title,
 * subtitle and whatever rides its slot — is chrome the layout gives away for
 * free, so only what is left counts as the screen saying something.
 */
function bodyBelowTheHead(string $full): string
{
    // The shell (sidebar, topbar, menu) talks on every screen: only what the
    // screen itself puts inside <main> counts as the screen talking.
    $main = mb_strpos($full, '<main');
    $html = $main === false ? $full : mb_substr($full, $main, (int) mb_strpos($full, '</main>') - $main);

    // Not the exact class: a caller that merges one of its own would slip past
    // an exact match and the head would count as the screen talking.
    $start = mb_strpos($html, 'page-head');

    if ($start === false) {
        return trim((string) preg_replace('/\s+/', ' ', strip_tags($html)));
    }

    // Walk from the head's own tag until its closing div: the slot nests, so
    // counting is the only way to know where the chrome ends.
    $cursor = (int) mb_strrpos(mb_substr($html, 0, $start), '<div');
    $depth = 0;
    $end = mb_strlen($html);

    for ($i = $cursor; $i < mb_strlen($html); $i++) {
        if (mb_substr($html, $i, 4) === '<div') {
            $depth++;
        } elseif (mb_substr($html, $i, 6) === '</div>') {
            $depth--;

            if ($depth === 0) {
                $end = $i + 6;

                break;
            }
        }
    }

    $body = mb_substr($html, 0, $cursor).mb_substr($html, $end);

    return trim((string) preg_replace('/\s+/', ' ', strip_tags($body)));
}

/*
|--------------------------------------------------------------------------
| Golden rule: a client whose business is not born yet still gets a screen
|--------------------------------------------------------------------------
| `Client::for()` leaves every piece null until the business exists, so a
| screen that reads its piece bare answers a 500 to the very user who has
| the most to lose: the one who just registered. Agenda ("day() on null")
| and Equipo ("members on null") shipped like that and the suite never
| noticed — every test seeded a business first.
|
| Capa B only on purpose: the break is a runtime property of the render,
| not a pattern in a file a PostToolUse hook could read. The menu is the
| source of the list, so a screen added tomorrow is covered without anyone
| maintaining an array here.
*/

test('every client screen renders for a user whose business is not born yet', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);

    $this->actingAs(User::factory()->create(['business_id' => null, 'email_verified_at' => now()])->refresh());

    $routes = Menu::query()
        ->where('panel', 'client')
        ->whereNotNull('route_name')
        ->pluck('route_name')
        ->unique()
        ->values();

    expect($routes)->not->toBeEmpty();

    $broken = [];

    foreach ($routes as $name) {
        $status = $this->get(route($name))->getStatusCode();

        // A redirect is a fine answer (the onboarding gate); a 5xx is not.
        if ($status >= 500) {
            $broken[] = $name.' answered '.$status;
        }
    }

    expect($broken)->toBe([]);
});

/*
| A 200 is not an answer. Statistics and Gana printed their title over a void
| for the user who just registered, while Equipo, Agenda and WhatsApp all named
| the step that unblocks them — the same defect as the 500s above, one HTTP
| code quieter, and the guardian walked right past it.
*/
test('every client screen tells a user whose business is not born yet what to do', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);

    $this->actingAs(User::factory()->create(['business_id' => null, 'email_verified_at' => now()])->refresh());

    $routes = Menu::query()
        ->where('panel', 'client')
        ->whereNotNull('route_name')
        ->pluck('route_name')
        ->unique()
        ->values();

    $mute = [];

    foreach ($routes as $name) {
        $response = $this->get(route($name));

        if ($response->getStatusCode() !== 200) {
            continue;
        }

        $body = bodyBelowTheHead((string) $response->getContent());

        // A sentence and its button; the shortest real one in the panel runs 74,
        // and the two that were mute measured zero.
        if (mb_strlen($body) < 40) {
            $mute[] = $name.' says '.mb_strlen($body).' chars below its head';
        }
    }

    expect($mute)->toBe([]);
});
