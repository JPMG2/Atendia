<?php

declare(strict_types=1);

use App\Models\AiModel;
use App\Models\User;
use Database\Seeders\AiModelSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Modelos de IA — the shots a human looks at
|--------------------------------------------------------------------------
| The screen is two lists of rows with two selects inside each task row,
| which is exactly where a layout gives way. Measured at the three widths in
| both themes, with the real twelve tasks and two priced models on screen.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
    $this->seed(AiModelSeeder::class);

    // A second lab, so a fallback is offered and the price list has two rows.
    AiModel::create([
        'provider' => 'anthropic', 'code' => 'claude-respaldo-1', 'label' => 'Claude respaldo',
        'effective_from' => '2026-10-01', 'prompt_per_million' => 8, 'cached_per_million' => 0.8,
        'completion_per_million' => 40, 'source' => 'Precio publicado, verificado el 04/10',
    ]);

    $admin = User::factory()->create(['name' => 'Administración de la plataforma']);
    $admin->assignRole('admin');

    $this->actingAs($admin->refresh());
});

test('the ai models screen holds at every width in both themes', function (string $label, int $width, int $height, bool $dark): void {
    $page = visit(route('admin.ai'))->resize($width, $height);

    if ($dark) {
        $page->click('@theme-toggle');
    }

    $page->assertNoJavaScriptErrors()
        ->assertSee('Modelos de IA')
        ->assertSee('Atiende a los clientes del negocio por WhatsApp')
        ->assertSee('GPT-6 Astra')
        ->screenshot(filename: 'admin-ai-'.$label.'-'.($dark ? 'dark' : 'light'));

    $overflow = $page->script('document.documentElement.scrollWidth - window.innerWidth');

    expect((int) $overflow)->toBeLessThanOrEqual(0);
})->with([
    'desktop light' => ['desktop', 1280, 900, false],
    'desktop dark' => ['desktop', 1280, 900, true],
    'tablet light' => ['tablet', 900, 900, false],
    'phone light' => ['phone', 390, 844, false],
    'phone dark' => ['phone', 390, 844, true],
]);
