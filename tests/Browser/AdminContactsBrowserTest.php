<?php

declare(strict_types=1);

use App\Models\PlatformContact;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Radar de personas — the shots a human looks at
|--------------------------------------------------------------------------
| Six columns of mostly figures, and a tag that only some rows wear: the
| width to watch is the phone, where the table has to stack by label.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);

    PlatformContact::factory()->create([
        'phone' => '5492995529100',
        'name' => 'Marcela Gutiérrez de la Fuente',
        'businesses_count' => 3,
        'conversations_count' => 14,
        'country_code' => 'AR',
        'language' => 'es',
        'first_seen_at' => now()->subMonths(3),
        'last_activity_at' => now()->subHours(2),
    ]);

    PlatformContact::factory()->create([
        'phone' => '584121234567',
        'businesses_count' => 1,
        'conversations_count' => 2,
        'country_code' => 'VE',
        'first_seen_at' => now()->subDays(9),
        'last_activity_at' => now()->subDays(1),
    ]);

    $admin = User::factory()->create(['name' => 'Administración de la plataforma']);
    $admin->assignRole('admin');

    $this->actingAs($admin->refresh());
});

test('the radar holds at every width in both themes', function (string $label, int $width, int $height, bool $dark): void {
    $page = visit(route('admin.contacts'))->resize($width, $height);

    if ($dark) {
        $page->click('@theme-toggle');
    }

    $page->assertNoJavaScriptErrors()
        ->assertSee('Radar de personas')
        ->assertSee('Marcela Gutiérrez de la Fuente')
        ->assertSee('Comparte 3 negocios')
        ->screenshot(filename: 'admin-contacts-'.$label.'-'.($dark ? 'dark' : 'light'));

    $overflow = $page->script('document.documentElement.scrollWidth - window.innerWidth');

    expect((int) $overflow)->toBeLessThanOrEqual(0);

    // The page not overflowing says nothing about the table: a column cut off
    // inside its own card hides behind a scrollbar nobody discovers.
    $cut = $page->script(
        'Array.from(document.querySelectorAll(".pay-table-wrap"))
            .map(w => w.scrollWidth - w.clientWidth)
            .reduce((a, b) => Math.max(a, b), 0)'
    );

    expect((int) $cut)->toBe(0);
})->with([
    'desktop light' => ['desktop', 1280, 900, false],
    'phone light' => ['phone', 390, 844, false],
    'phone dark' => ['phone', 390, 844, true],
]);
