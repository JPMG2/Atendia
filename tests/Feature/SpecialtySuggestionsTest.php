<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\BusinessActivity;
use App\Models\BusinessSector;
use App\Models\Specialty;
use App\Models\SuggestedService;
use App\Models\User;
use Database\Seeders\BusinessActivitySeeder;
use Database\Seeders\BusinessSectorSeeder;
use Database\Seeders\ServiceAttributeSeeder;
use Database\Seeders\ServiceModalitySeeder;
use Database\Seeders\ServiceTypeSeeder;
use Database\Seeders\SpecialtySeeder;
use Database\Seeders\SpecialtySuggestionSeeder;
use Database\Seeders\SuggestedServiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Queue::fake();
    app()->setLocale('es');
});

/** The whole catalog the specialty suggestions hang off, seeded in its real order. */
function seedSpecialtySuggestions(): void
{
    foreach ([
        BusinessSectorSeeder::class, BusinessActivitySeeder::class, ServiceModalitySeeder::class,
        ServiceAttributeSeeder::class, ServiceTypeSeeder::class, SuggestedServiceSeeder::class,
        SpecialtySeeder::class, SpecialtySuggestionSeeder::class,
    ] as $seeder) {
        test()->seed($seeder);
    }
}

function businessMarking(string $activityCode, array $specialtyCodes): Business
{
    $business = Business::factory()->create();
    $business->syncActivities(BusinessActivity::where('code', $activityCode)->value('id'));
    $business->syncSpecialties($specialtyCodes);

    $user = User::factory()->create();
    $user->business()->associate($business)->save();
    test()->actingAs($user);

    return $business;
}

test('every one of the 49 specialties brings at least three typed suggestions', function (): void {
    seedSpecialtySuggestions();

    expect(Specialty::query()->count())->toBe(49)
        ->and(Specialty::query()->doesntHave('suggestedServices')->pluck('code')->all())->toBe([])
        ->and(Specialty::query()->withCount('suggestedServices')->get()->contains(fn (Specialty $s): bool => $s->suggested_services_count < 3))->toBeFalse()
        ->and(SuggestedService::query()->has('specialties')->whereNull('service_type_id')->count())->toBe(0);
});

test('running the seeder twice duplicates nothing', function (): void {
    seedSpecialtySuggestions();

    $suggestions = SuggestedService::query()->count();
    $links = DB::table('specialty_suggested_service')->count();

    $this->seed(SpecialtySuggestionSeeder::class);

    expect(SuggestedService::query()->count())->toBe($suggestions)
        ->and(DB::table('specialty_suggested_service')->count())->toBe($links);
});

test('the general lists of an activity and of its sector do not change: the specialty ones stay out', function (): void {
    seedSpecialtySuggestions();

    $general = BusinessActivity::suggestionsName('consultorio-medico');

    expect($general)->toBe([
        'Consulta clínica', 'Control de rutina', 'Electrocardiograma', 'Ecografía',
        'Análisis de laboratorio', 'Certificado médico', 'Apto físico', 'Consulta a domicilio',
    ])->and(BusinessActivity::suggestionsName('restaurante'))->not->toContain('Ceviche', 'Lasaña')
        ->and(BusinessSector::where('code', 'gastronomia')->sole()->suggestedServices(500)->pluck('name')->all())->not->toContain('Ceviche', 'Lasaña')
        ->and(BusinessSector::where('code', 'salud')->sole()->suggestedServices(500)->pluck('name')->all())->not->toContain('Consulta de neurología');
});

test('a name the activity already offers in general is never captured by a specialty', function (): void {
    seedSpecialtySuggestions();

    $general = SuggestedService::query()->where('name', 'Control de rutina')->sole();
    $general->specialties()->detach();

    // A specialty list that happens to repeat a general name leaves it general.
    $row = SuggestedService::query()->firstOrCreate(
        ['business_activity_id' => $general->business_activity_id, 'name' => 'Control de rutina'],
        ['service_type_id' => $general->service_type_id],
    );

    expect($row->is($general))->toBeTrue()
        ->and($row->specialties()->exists())->toBeFalse()
        ->and(BusinessActivity::suggestionsName('consultorio-medico'))->toContain('Control de rutina');
});

test('a business is offered what the specialties it marked suggest, and nothing of the others', function (): void {
    seedSpecialtySuggestions();

    $business = businessMarking('consultorio-medico', ['neurologia']);

    expect($business->specialtySuggestionNames())->toContain('Consulta de neurología', 'Electroencefalograma')
        ->not->toContain('Consulta de geriatría')
        ->and(Business::factory()->create()->specialtySuggestionNames())->toBe([]);
});

test('dishes of a cuisine are products, so they go to the products step and not the services one', function (): void {
    seedSpecialtySuggestions();

    $business = businessMarking('restaurante', ['cocina-peruana']);

    expect($business->specialtySuggestionNames(goods: true))->toContain('Ceviche', 'Lomo saltado')
        ->and($business->specialtySuggestionNames())->not->toContain('Ceviche');
});

test('the wizard offers the marked specialties first on the services step', function (): void {
    seedSpecialtySuggestions();
    businessMarking('consultorio-medico', ['neurologia']);

    $suggestions = Livewire::test('business.step-services', ['sector' => 'salud', 'activity' => 'consultorio-medico'])
        ->assertSee('Consulta de neurología')
        ->assertSee('Consulta clínica')
        ->instance()->suggestions;

    expect($suggestions[0])->toBe('Consulta de neurología');
});

test('the wizard offers the dishes of the marked cuisines on the products step', function (): void {
    seedSpecialtySuggestions();
    businessMarking('restaurante', ['cocina-italiana']);

    Livewire::test('business.step-products', ['sector' => 'gastronomia', 'activity' => 'restaurante'])
        ->assertSee('Lasaña')
        ->assertSee('Ñoquis');
});

test('a business that marked nothing sees exactly the lists it saw before', function (): void {
    seedSpecialtySuggestions();
    businessMarking('consultorio-medico', []);

    $suggestions = Livewire::test('business.step-services', ['sector' => 'salud', 'activity' => 'consultorio-medico'])->instance()->suggestions;

    expect($suggestions)->toBe(BusinessActivity::suggestionsName('consultorio-medico'));
});
