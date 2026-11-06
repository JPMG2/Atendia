<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\BusinessActivity;
use App\Models\Country;
use App\Models\Province;
use App\Models\User;
use Database\Seeders\BusinessActivitySeeder;
use Database\Seeders\BusinessSectorSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\ServiceAttributeSeeder;
use Database\Seeders\ServiceModalitySeeder;
use Database\Seeders\ServiceTypeSeeder;
use Database\Seeders\SpecialtySeeder;
use Database\Seeders\SpecialtySuggestionSeeder;
use Database\Seeders\SuggestedServiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| What a marked specialty brings with it, seen in a real browser
|--------------------------------------------------------------------------
| The wizard's services step offers the specialty's own services first, and the
| public booking link tells the customer which specialties the business marked.
*/

beforeEach(function (): void {
    Queue::fake();
    app()->setLocale('es');

    foreach ([
        RolesAndPermissionsSeeder::class, BusinessSectorSeeder::class, BusinessActivitySeeder::class,
        ServiceModalitySeeder::class, ServiceAttributeSeeder::class, ServiceTypeSeeder::class,
        SuggestedServiceSeeder::class, SpecialtySeeder::class, SpecialtySuggestionSeeder::class,
    ] as $seeder) {
        $this->seed($seeder);
    }
});

test('the wizard offers the services of the specialty marked on the previous step first', function (): void {
    $country = Country::factory()->create(['name' => 'Argentina', 'code' => 'ARG']);
    Province::factory()->create(['name' => 'Buenos Aires', 'country_id' => $country->id]);
    $this->actingAs(User::factory()->create()->refresh());

    $page = visit('/alta')->resize(390, 900);

    $page->type('#if-name', 'Clínica Vida');
    $page->type('#if-country_id', 'Argentina')->click('Argentina')->wait(1);
    $page->type('#if-province_id', 'Buenos')->click('Buenos Aires');

    $page->script("[...document.querySelectorAll('.wizard-chip')].find(chip => chip.textContent.trim() === 'Salud').click()");
    $page->assertSee('Consultorio médico');
    $page->script("[...document.querySelectorAll('.wizard-chip')].find(chip => chip.textContent.trim() === 'Consultorio médico').click()");
    $page->type('#if-specialties', 'neuro')->click('Neurología');

    // The list stays open to take more marks and covers the button: touching elsewhere closes it, like a person would.
    $page->script('document.activeElement.blur()');
    $page->click('Continuar')->wait(2);

    $page->assertSee('Consulta de neurología')
        ->assertSee('Evaluación de cefaleas y migrañas')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'specialty-wizard-services');

    expect(Business::query()->sole()->specialtyCodes())->toBe(['neurologia']);
});

test('the wizard offers the dishes of the cuisine marked on the products step', function (): void {
    $country = Country::factory()->create(['name' => 'Argentina', 'code' => 'ARG']);
    Province::factory()->create(['name' => 'Buenos Aires', 'country_id' => $country->id]);
    $this->actingAs(User::factory()->create()->refresh());

    $page = visit('/alta')->resize(390, 900);

    $page->type('#if-name', 'Trattoria Sol');
    $page->type('#if-country_id', 'Argentina')->click('Argentina')->wait(1);
    $page->type('#if-province_id', 'Buenos')->click('Buenos Aires');

    $page->script("[...document.querySelectorAll('.wizard-chip')].find(chip => chip.textContent.trim() === 'Gastronomía').click()");
    $page->assertSee('Restaurante');
    $page->script("[...document.querySelectorAll('.wizard-chip')].find(chip => chip.textContent.trim() === 'Restaurante').click()");
    $page->type('#if-specialties', 'italiana')->click('Cocina italiana');
    $page->script('document.activeElement.blur()');
    $page->click('Continuar')->wait(2);

    // Services step first, skipped: the dishes of a cuisine are products.
    $page->click('Saltar por ahora')->wait(1);

    $page->assertSee('Lasaña')
        ->assertSee('Ñoquis')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'specialty-wizard-products');
});

test('the public booking link shows the specialties the business marked', function (): void {
    $business = Business::factory()->create(['name' => 'Centro Médico Vida', 'appointments_enabled' => true]);
    $business->syncActivities(BusinessActivity::where('code', 'consultorio-medico')->value('id'));
    $business->syncSpecialties(['neurologia', 'medicina-interna']);

    $page = visit(route('booking.public', ['code' => $business->booking_code]))->resize(390, 844);

    $page->assertSee('Centro Médico Vida')
        ->assertSee('Especialidad: Medicina interna, Neurología')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'specialty-booking-public');
});
