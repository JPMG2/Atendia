<?php

declare(strict_types=1);

use App\Actions\Catalog\RepeatSeasonalWindow;
use App\Models\DemoTag;
use App\Models\SeasonalWindow;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Seasons over the hero demo
|--------------------------------------------------------------------------
| One mechanism: a dated window, and rows that override the all-year row
| field by field. These pin the two halves nobody checks by looking —
| that a variant only changes what it fills, and that the day the window
| closes the evergreen comes back whole, with no deploy and no edit.
*/

function evergreen(array $attributes = []): DemoTag
{
    return DemoTag::factory()->create(['slug' => 'ferreteria', 'label' => 'Ferretería', ...$attributes]);
}

test('with no season running the hero reads the all-year rows', function (): void {
    evergreen(['business_name' => 'Ferretería El Tornillo']);

    expect(DemoTag::resolved())->toHaveCount(1)
        ->and(DemoTag::resolved()[0]['label'])->toBe('Ferretería')
        ->and(DemoTag::resolved()[0]['season'])->toBeNull();
});

test('a variant overrides what it fills and inherits the rest', function (): void {
    $tag = evergreen(['business_name' => 'Ferretería El Tornillo', 'noun' => 'ferretería']);
    $window = SeasonalWindow::factory()->create(['name' => 'Navidad 2026']);

    DemoTag::factory()->variantOf($tag, $window)->create([
        'label' => 'Ferretería 🎄',
        'chips' => ['¿Abren el 24?'],
    ]);

    $live = DemoTag::resolved()[0];

    expect($live['label'])->toBe('Ferretería 🎄')
        ->and($live['chips'])->toBe(['¿Abren el 24?'])
        // Untouched by the variant, so it still comes from the all-year row.
        ->and($live['name'])->toBe('Ferretería El Tornillo')
        ->and($live['noun'])->toBe('ferretería')
        ->and($live['season'])->toBe('Navidad 2026');
});

test('the day after a season ends the all-year row is back, whole', function (): void {
    $tag = evergreen();
    $window = SeasonalWindow::factory()->create(['ends_at' => SeasonalWindow::today()->addDay()]);

    DemoTag::factory()->variantOf($tag, $window)->create(['label' => 'Ferretería 🎄']);

    expect(DemoTag::resolved()[0]['label'])->toBe('Ferretería 🎄');

    $afterwards = DemoTag::resolved(SeasonalWindow::today()->addDays(2));

    expect($afterwards[0]['label'])->toBe('Ferretería')
        ->and($afterwards[0]['season'])->toBeNull();
});

test('a season switched off never speaks, even inside its dates', function (): void {
    $tag = evergreen();
    $window = SeasonalWindow::factory()->create(['is_active' => false]);

    DemoTag::factory()->variantOf($tag, $window)->create(['label' => 'Ferretería 🎄']);

    expect(DemoTag::resolved()[0]['label'])->toBe('Ferretería');
});

test('two seasons on the same day are settled by priority, not by luck', function (): void {
    $tag = evergreen();
    $weak = SeasonalWindow::factory()->create(['name' => 'Verano', 'priority' => 1]);
    $strong = SeasonalWindow::factory()->create(['name' => 'Navidad 2026', 'priority' => 9]);

    DemoTag::factory()->variantOf($tag, $weak)->create(['label' => 'Ferretería ☀️']);
    DemoTag::factory()->variantOf($tag, $strong)->create(['label' => 'Ferretería 🎄']);

    expect(DemoTag::resolved()[0]['label'])->toBe('Ferretería 🎄')
        ->and(DemoTag::resolved()[0]['season'])->toBe('Navidad 2026');
});

test('a tag switched off leaves the hero, variant or not', function (): void {
    evergreen(['is_active' => false]);

    expect(DemoTag::resolved())->toBe([]);
});

test('the landing paints the rows, in their order', function (): void {
    evergreen(['sort_order' => 20]);
    DemoTag::factory()->create(['slug' => 'kiosco', 'label' => 'Kiosco', 'sort_order' => 10]);

    $this->get('/')
        ->assertSuccessful()
        ->assertSeeInOrder(['data-demo-rubro="kiosco"', 'data-demo-rubro="ferreteria"'], false);
});

test('a season carried into next year brings its variants, switched off', function (): void {
    $tag = evergreen();
    $window = SeasonalWindow::factory()->create([
        'name' => 'Navidad 2026',
        'starts_at' => CarbonImmutable::parse('2026-12-20'),
        'ends_at' => CarbonImmutable::parse('2026-12-27'),
        'priority' => 5,
    ]);
    DemoTag::factory()->variantOf($tag, $window)->create(['label' => 'Ferretería 🎄']);

    $copy = app(RepeatSeasonalWindow::class)->handle($window->id);

    expect($copy->name)->toBe('Navidad 2027')
        ->and($copy->starts_at->toDateString())->toBe('2027-12-20')
        ->and($copy->ends_at->toDateString())->toBe('2027-12-27')
        ->and($copy->priority)->toBe(5)
        // Off on purpose: written a year early, read before it speaks.
        ->and($copy->is_active)->toBeFalse()
        ->and($copy->demoTags()->sole()->label)->toBe('Ferretería 🎄');
});

test('repeating twice does not leave two copies of the same year', function (): void {
    $window = SeasonalWindow::factory()->create(['name' => 'Navidad 2026']);

    app(RepeatSeasonalWindow::class)->handle($window->id);

    expect(app(RepeatSeasonalWindow::class)->handle($window->id))->toBeNull()
        ->and(SeasonalWindow::query()->count())->toBe(2);
});

test('a name with no year gets one appended instead of being rewritten', function (): void {
    $window = SeasonalWindow::factory()->create([
        'name' => 'Fin de año',
        'starts_at' => CarbonImmutable::parse('2026-12-28'),
        'ends_at' => CarbonImmutable::parse('2026-12-31'),
    ]);

    expect(app(RepeatSeasonalWindow::class)->handle($window->id)->name)->toBe('Fin de año 2027');
});

test('flipping a season from its row reaches the landing with no editor', function (): void {
    $tag = evergreen();
    $window = SeasonalWindow::factory()->create();
    DemoTag::factory()->variantOf($tag, $window)->create(['label' => 'Ferretería 🎄']);

    expect(DemoTag::resolved()[0]['label'])->toBe('Ferretería 🎄');

    SeasonalWindow::flipActive($window->id);

    expect($window->fresh()->is_active)->toBeFalse()
        // The cache would have answered the old thing without the model hook.
        ->and(DemoTag::resolved()[0]['label'])->toBe('Ferretería');
});

test('the scripted conversation survives the round trip through her textarea', function (): void {
    $pool = [
        ['side' => 'in', 'text' => '¿Tienen cinta de teflón?'],
        ['side' => 'out', 'text' => 'Sí, en stock.'],
    ];

    $text = DemoTag::scriptToText($pool);

    expect($text)->toBe("cliente: ¿Tienen cinta de teflón?\nasistente: Sí, en stock.")
        ->and(DemoTag::textToScript($text))->toBe($pool);
});

test('a scripted line with no speaker is kept as the customer, never dropped', function (): void {
    expect(DemoTag::textToScript("hola\nasistente: buenas"))->toBe([
        ['side' => 'in', 'text' => 'hola'],
        ['side' => 'out', 'text' => 'buenas'],
    ]);
});
