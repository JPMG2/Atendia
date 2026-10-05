<?php

declare(strict_types=1);

use App\Models\PlatformSetting;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\PlatformSettingSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Ajustes de la plataforma — the shots a human looks at
|--------------------------------------------------------------------------
| Grouped by the task each knob belongs to, with what it does beside it and
| what the code ships with in its own column. A knob whose effect she has to
| guess is one she leaves alone.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
    $this->seed(PlatformSettingSeeder::class);

    // One knob off its default, so the badge and the comparison have
    // something real to show.
    PlatformSetting::where('key', 'analysis.idle_hours')->update(['value' => '6']);

    $admin = User::factory()->create(['name' => 'Administración de la plataforma']);
    $admin->assignRole('admin');

    $this->actingAs($admin->refresh());
});

test('the settings screen holds at every width in both themes', function (string $label, int $width, int $height, bool $dark): void {
    $page = visit(route('admin.settings'))->resize($width, $height);

    if ($dark) {
        $page->click('@theme-toggle');
    }

    $page->assertNoJavaScriptErrors()
        ->assertSee('Ajustes de la plataforma')
        ->assertSee('Cuándo salen los mensajes automáticos')
        ->assertSee('Silencio para dar por terminada una charla')
        // The consequence is on screen; the config path never is.
        ->assertSee('puede cortar una charla viva')
        ->assertDontSee('analysis.idle_hours')
        ->screenshot(filename: 'admin-settings-'.$label.'-'.($dark ? 'dark' : 'light'));

    $overflow = $page->script('document.documentElement.scrollWidth - window.innerWidth');

    expect((int) $overflow)->toBeLessThanOrEqual(0);

    $cut = $page->script(
        'Array.from(document.querySelectorAll(".pay-table-wrap"))
            .map(w => w.scrollWidth - w.clientWidth)
            .reduce((a, b) => Math.max(a, b), 0)'
    );

    expect((int) $cut)->toBe(0);
})->with([
    'desktop light' => ['desktop', 1280, 1000, false],
    'desktop dark' => ['desktop', 1280, 1000, true],
    'phone light' => ['phone', 390, 844, false],
]);
