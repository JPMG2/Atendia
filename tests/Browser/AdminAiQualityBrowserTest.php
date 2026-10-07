<?php

declare(strict_types=1);

use App\Models\AskFeedback;
use App\Models\AssistantRating;
use App\Models\Business;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Calidad de la IA — the shots a human looks at
|--------------------------------------------------------------------------
| Two boards in one screen, and the thing to check by EYE is that the share
| never appears alone: beside it has to sit the number of marks it is built
| on, and the warning when they are too few to mean anything.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);

    $failing = Business::factory()->create(['name' => 'Centro Odontológico Integral del Sur']);
    $fine = Business::factory()->create(['name' => 'Kiosco La Esquina']);

    AssistantRating::factory()->bad()->count(4)->create(['business_id' => $failing->id]);
    AssistantRating::factory()->count(2)->create(['business_id' => $failing->id]);
    AssistantRating::factory()->count(6)->create(['business_id' => $fine->id]);

    AskFeedback::query()->create([
        'business_id' => $failing->id,
        'question' => '¿Cuántos turnos tengo mañana?',
        'answer' => 'No tenés turnos cargados para mañana.',
        'rating' => AskFeedback::DOWN,
    ]);

    $admin = User::factory()->create(['name' => 'Administración de la plataforma']);
    $admin->assignRole('admin');

    $this->actingAs($admin->refresh());
});

test('the quality screen holds at every width and never shows a share alone', function (string $label, int $width, int $height, bool $dark): void {
    $page = visit(route('admin.ai-quality'))->resize($width, $height);

    if ($dark) {
        $page->click('@theme-toggle');
    }

    $page->assertNoJavaScriptErrors()
        ->assertSee('Calidad de la IA')
        ->assertSee('Centro Odontológico Integral del Sur')
        // Singular on purpose: "sobre 1 marcadas" is what a share printed
        // without its plural rule looks like, and it reads as carelessness.
        ->assertSee('sobre 1 respuesta marcada')
        ->assertSee('Es una sola marca')
        ->assertSee('¿Cuántos turnos tengo mañana?')
        ->screenshot(filename: 'admin-ai-quality-'.$label.'-'.($dark ? 'dark' : 'light'));

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
    'phone light' => ['phone', 390, 844, false],
    'phone dark' => ['phone', 390, 844, true],
]);
