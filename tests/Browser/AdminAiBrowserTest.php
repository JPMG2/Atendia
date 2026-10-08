<?php

declare(strict_types=1);

use App\Enums\AiCapability;
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

    // Both OpenAI keys and a second lab, so the selects offer something and a
    // fallback exists; plus a voice model, priced by the minute.
    config([
        'ai.providers.openai.key' => 'test-key-a',
        'ai.providers.openai-app.key' => 'test-key-b',
        'ai.providers.anthropic.key' => 'test-key-d',
    ]);

    AiModel::create([
        'provider' => 'anthropic', 'code' => 'claude-respaldo-1', 'label' => 'Claude respaldo',
        'effective_from' => '2026-10-01', 'prompt_per_million' => 8, 'cached_per_million' => 0.8,
        'completion_per_million' => 40, 'source' => 'Precio publicado, verificado el 04/10',
    ]);

    AiModel::create([
        'provider' => 'openai', 'capability' => AiCapability::Transcription, 'code' => 'voz-de-prueba',
        'label' => 'Voz de prueba', 'effective_from' => '2026-10-01', 'per_minute' => 0.006,
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
        ->assertSee('Transcribe el audio que manda el cliente')
        ->assertSee('no se cambia suelto');

    // The three tabs, each one measured and shot: a tab nobody opens is where
    // a layout breaks unseen.
    foreach (['assign' => null, 'catalog' => 'Catálogo', 'connections' => 'Conexiones'] as $tab => $name) {
        if ($name !== null) {
            $page->click($name);
        }

        $page->screenshot(filename: 'admin-ai-'.$tab.'-'.$label.'-'.($dark ? 'dark' : 'light'));

        if ($tab === 'catalog') {
            $page->assertSee('GPT-6 Astra')->assertSee('Voz de prueba');

            // The add form changes shape with what the model does: shot with it open.
            $page->click('Agregar modelo')->assertSee('Qué hace')
                ->screenshot(filename: 'admin-ai-catalog-form-'.$label.'-'.($dark ? 'dark' : 'light'));
        }

        if ($tab === 'connections') {
            $page->assertSee('openai-app');
        }

        expect((int) $page->script('document.documentElement.scrollWidth - window.innerWidth'))->toBeLessThanOrEqual(0);

        // The page not overflowing says nothing about the tables: a column cut
        // off inside its own card hides behind a scrollbar nobody discovers,
        // and the page-level check passes green on exactly that.
        $cut = $page->script(
            'Array.from(document.querySelectorAll(".pay-table-wrap"))
                .filter(w => w.offsetParent !== null)
                .map(w => w.scrollWidth - w.clientWidth)
                .reduce((a, b) => Math.max(a, b), 0)'
        );

        expect((int) $cut)->toBe(0);
    }

})->with([
    'desktop light' => ['desktop', 1280, 900, false],
    'desktop dark' => ['desktop', 1280, 900, true],
    'tablet light' => ['tablet', 900, 900, false],
    'phone light' => ['phone', 390, 844, false],
    'phone dark' => ['phone', 390, 844, true],
]);
