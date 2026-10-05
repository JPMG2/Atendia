<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Auditoría — the shots a human looks at
|--------------------------------------------------------------------------
| The strong changes painted, the ordinary ones out of the way, and the
| person as the filter — because the question is always about somebody.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
    Activity::query()->delete();

    $admin = User::factory()->create(['name' => 'Juan', 'email' => 'admin@atendia.test', 'email_verified_at' => now()]);
    $admin->assignRole('admin');
    Activity::query()->delete();

    $this->actingAs($admin->refresh());

    // One of each kind the screen tells apart.
    Business::factory()->create(['name' => 'Kiosco La Esquina']);
    Business::factory()->create(['name' => 'Panadería del Centro'])->delete();

    $person = User::factory()->create(['name' => 'Rocío Paz', 'email' => 'rocio@atendia.test', 'email_verified_at' => now()]);
    $person->assignRole('support');

    // And something nobody signed in did.
    Auth::logout();
    Business::factory()->create(['name' => 'Alta automática']);
    $this->actingAs($admin);
});

test('the audit screen holds at every width in both themes', function (string $label, int $width, int $height, bool $dark): void {
    $page = visit(route('admin.audit'))->resize($width, $height);

    if ($dark) {
        $page->click('@theme-toggle');
    }

    $page->assertNoJavaScriptErrors()
        ->assertSee('Auditoría')
        // A deletion and an access change: what she must not have to hunt for.
        ->assertSee('Panadería del Centro')
        ->assertSee('Dio acceso')
        // The ordinary creation stays out until she asks for it.
        ->assertDontSee('Kiosco La Esquina')
        ->screenshot(filename: 'admin-audit-'.$label.'-'.($dark ? 'dark' : 'light'));

    $overflow = $page->script('document.documentElement.scrollWidth - window.innerWidth');

    expect((int) $overflow)->toBeLessThanOrEqual(0);

    $cut = $page->script(
        'Array.from(document.querySelectorAll(".pay-table-wrap"))
            .map(w => w.scrollWidth - w.clientWidth)
            .reduce((a, b) => Math.max(a, b), 0)'
    );

    expect((int) $cut)->toBe(0);
})->with([
    'desktop light' => ['desktop', 1280, 900, false],
    'desktop dark' => ['desktop', 1280, 900, true],
    'phone light' => ['phone', 390, 844, false],
]);
