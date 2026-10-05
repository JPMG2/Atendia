<?php

declare(strict_types=1);

use App\Models\AiModel;
use App\Models\AiUsage;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Consumo de IA — the shots a human looks at
|--------------------------------------------------------------------------
| Three columns of figures per row is where a layout gives way, and one of
| the rows has no published price on purpose: the screen has to say it at
| every width instead of quietly showing a zero.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);

    AiModel::create([
        'provider' => 'openai', 'code' => 'gpt-6-astra', 'label' => 'GPT-6 Astra',
        'effective_from' => now()->startOfMonth()->subYear(),
        'prompt_per_million' => 10, 'cached_per_million' => 1, 'completion_per_million' => 50,
    ]);

    $spenders = [
        ['Centro Odontológico Integral del Sur', 4_200_000, 1_800_000, 'gpt-6-astra'],
        ['Laboratorio Vida', 900_000, 250_000, 'gpt-6-astra'],
        ['Kiosco La Esquina', 120_000, 30_000, 'modelo-sin-precio'],
    ];

    foreach ($spenders as [$name, $input, $output, $model]) {
        $business = Business::factory()->create(['name' => $name]);
        $conversation = Conversation::factory()->create(['business_id' => $business->id]);

        ConversationMessage::factory()->for($conversation)->create([
            'business_id' => $business->id, 'audio_seconds' => 95,
        ]);
        ConversationMessage::factory()->out()->for($conversation)->create(['business_id' => $business->id]);

        AiUsage::query()->create([
            'business_id' => $business->id, 'kind' => 'AsistenteAtendia', 'model' => $model,
            'input_tokens' => $input, 'cached_tokens' => (int) ($input / 2), 'output_tokens' => $output,
        ]);
        AiUsage::query()->create([
            'business_id' => $business->id, 'kind' => AiUsage::EMBEDDINGS,
            'model' => 'text-embedding-3-small', 'input_tokens' => 40_000,
        ]);
    }

    // Months behind the one on screen, so the trend has a shape to draw and
    // is not twelve flat zeros pretending to be a year.
    $first = Business::where('name', 'Centro Odontológico Integral del Sur')->firstOrFail();

    foreach ([1 => 900_000, 2 => 2_400_000, 3 => 1_100_000, 5 => 300_000] as $back => $input) {
        AiUsage::query()->forceCreate([
            'business_id' => $first->id, 'kind' => 'AsistenteAtendia', 'model' => 'gpt-6-astra',
            'input_tokens' => $input, 'cached_tokens' => (int) ($input / 3), 'output_tokens' => 120_000,
            'created_at' => now()->startOfMonth()->subMonths($back)->addDay(),
            'updated_at' => now()->startOfMonth()->subMonths($back)->addDay(),
        ]);
    }

    // The platform's own calls: the hero demo has no business behind it.
    AiUsage::query()->create([
        'business_id' => null, 'kind' => 'AsistenteAtendia', 'model' => 'gpt-6-astra',
        'input_tokens' => 310_000, 'cached_tokens' => 20_000, 'output_tokens' => 44_000,
    ]);

    $admin = User::factory()->create(['name' => 'Administración de la plataforma']);
    $admin->assignRole('admin');

    $this->actingAs($admin->refresh());
});

test('the ai spend screen holds at every width in both themes', function (string $label, int $width, int $height, bool $dark): void {
    $page = visit(route('admin.ai-usage'))->resize($width, $height);

    if ($dark) {
        $page->click('@theme-toggle');
    }

    $page->assertNoJavaScriptErrors()
        ->assertSee('Consumo de IA')
        ->assertSee('Centro Odontológico Integral del Sur')
        ->assertSee('Plataforma')
        ->assertSee('Total del mes')
        ->assertSee('lo que el caché le quitó a la factura')
        ->assertSee('su modelo no tiene precio publicado')
        ->assertPresent('.sparkline svg polyline')
        ->assertPresent('tr.is-over')
        ->screenshot(filename: 'admin-ai-usage-'.$label.'-'.($dark ? 'dark' : 'light'));

    $overflow = $page->script('document.documentElement.scrollWidth - window.innerWidth');

    expect((int) $overflow)->toBeLessThanOrEqual(0);

    // The page not overflowing says nothing about the table: a column cut off
    // inside its own card hides behind a scrollbar nobody discovers, and that
    // is exactly what the page-level check passes green on.
    $cut = $page->script(
        'Array.from(document.querySelectorAll(".pay-table-wrap"))
            .map(w => w.scrollWidth - w.clientWidth)
            .reduce((a, b) => Math.max(a, b), 0)'
    );

    expect((int) $cut)->toBe(0);
})->with([
    'desktop light' => ['desktop', 1280, 900, false],
    'desktop dark' => ['desktop', 1280, 900, true],
    'tablet light' => ['tablet', 900, 900, false],
    'phone light' => ['phone', 390, 844, false],
    'phone dark' => ['phone', 390, 844, true],
]);
