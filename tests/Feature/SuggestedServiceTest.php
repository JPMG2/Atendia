<?php

declare(strict_types=1);

use App\Models\BusinessActivity;
use App\Models\BusinessSector;
use App\Models\SuggestedService;
use Database\Seeders\BusinessActivitySeeder;
use Database\Seeders\BusinessSectorSeeder;
use Database\Seeders\ServiceAttributeSeeder;
use Database\Seeders\ServiceModalitySeeder;
use Database\Seeders\ServiceTypeSeeder;
use Database\Seeders\SuggestedServiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Suggested services — the GBP-style layer
|--------------------------------------------------------------------------
| Concrete services the catalog proposes per activity ("Corte de caballero",
| "Menú del día"), each hanging from its TYPE so adopting one is born typed.
| The wizard pools them per sector while only the sector is known.
*/

function seedSuggestedCatalog(): void
{
    test()->seed(BusinessSectorSeeder::class);
    test()->seed(BusinessActivitySeeder::class);
    test()->seed(ServiceModalitySeeder::class);
    test()->seed(ServiceAttributeSeeder::class);
    test()->seed(ServiceTypeSeeder::class);
    test()->seed(SuggestedServiceSeeder::class);
}

test('every active activity has concrete suggestions of its own trade', function (): void {
    seedSuggestedCatalog();

    expect(BusinessActivity::where('is_active', true)->doesntHave('suggestedServices')->pluck('code')->all())
        ->toBe([]);
});

test('every suggestion carries its type, so an adopted one is born typed', function (): void {
    seedSuggestedCatalog();

    expect(SuggestedService::whereNull('service_type_id')->count())->toBe(0);
});

test('running the seeder twice does not duplicate anything', function (): void {
    seedSuggestedCatalog();

    $total = SuggestedService::count();

    test()->seed(SuggestedServiceSeeder::class);

    expect(SuggestedService::count())->toBe($total);
});

test('a sector pools its activities\' suggestions, deduped by name', function (): void {
    $sector = BusinessSector::factory()->create();
    $bakery = BusinessActivity::factory()->create(['business_sector_id' => $sector->id]);
    $cafe = BusinessActivity::factory()->create(['business_sector_id' => $sector->id]);

    // The classic repeats across sibling trades: the wizard must offer it once.
    SuggestedService::factory()->create(['business_activity_id' => $bakery->id, 'name' => 'Envío a domicilio', 'sort_order' => 1]);
    SuggestedService::factory()->create(['business_activity_id' => $cafe->id, 'name' => 'Envío a domicilio', 'sort_order' => 1]);
    SuggestedService::factory()->create(['business_activity_id' => $cafe->id, 'name' => 'Merienda para dos', 'sort_order' => 2]);

    expect($sector->suggestedServices()->pluck('name')->all())
        ->toBe(['Envío a domicilio', 'Merienda para dos']);
});

test('the sector pool hides inactive suggestions and caps the chips', function (): void {
    $sector = BusinessSector::factory()->create();
    $activity = BusinessActivity::factory()->create(['business_sector_id' => $sector->id]);

    SuggestedService::factory()->create(['business_activity_id' => $activity->id, 'name' => 'Dormido', 'is_active' => false]);

    SuggestedService::factory()->count(15)->create(['business_activity_id' => $activity->id]);

    $pooled = $sector->suggestedServices();

    expect($pooled)->toHaveCount(12)
        ->and($pooled->pluck('name')->all())->not->toContain('Dormido');
});

test('an activity lists its suggestions in demand order', function (): void {
    $activity = BusinessActivity::factory()->create();

    SuggestedService::factory()->create(['business_activity_id' => $activity->id, 'name' => 'Segundo', 'sort_order' => 2]);
    SuggestedService::factory()->create(['business_activity_id' => $activity->id, 'name' => 'Primero', 'sort_order' => 1]);

    expect($activity->suggestedServices()->pluck('name')->all())->toBe(['Primero', 'Segundo']);
});
