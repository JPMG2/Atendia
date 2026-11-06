<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Country;
use App\Models\Province;
use App\Models\User;
use Database\Seeders\BusinessActivitySeeder;
use Database\Seeders\BusinessSectorSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\ServiceAttributeSeeder;
use Database\Seeders\ServiceModalitySeeder;
use Database\Seeders\ServiceTypeSeeder;
use Database\Seeders\SuggestedServiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Going back to step 2 and changing trade, the way a person does it
|--------------------------------------------------------------------------
| Health, a saved service, back to the first step, gastronomy: the health
| services must not follow her into the new trade.
*/

beforeEach(function (): void {
    Queue::fake();
    app()->setLocale('es');

    foreach ([
        RolesAndPermissionsSeeder::class, BusinessSectorSeeder::class, BusinessActivitySeeder::class,
        ServiceModalitySeeder::class, ServiceAttributeSeeder::class, ServiceTypeSeeder::class, SuggestedServiceSeeder::class,
    ] as $seeder) {
        $this->seed($seeder);
    }
});

/** Every mounted step has its own Continuar: only the visible one is the current step's. */
function pressVisibleContinue(object $page): void
{
    $page->script("[...document.querySelectorAll('button')].filter(b => b.textContent.trim() === 'Continuar' && b.offsetParent !== null)[0].click()");
    $page->wait(2);
}

function pressChip(object $page, string $text): void
{
    $page->script("[...document.querySelectorAll('.wizard-chip, .wizard-chips button')].find(chip => chip.textContent.replace('+', '').trim() === '{$text}').click()");
    $page->wait(1);
}

test('the services of the old trade do not follow her when she changes trade', function (): void {
    $country = Country::factory()->create(['name' => 'Argentina', 'code' => 'ARG']);
    Province::factory()->create(['name' => 'Buenos Aires', 'country_id' => $country->id]);
    $this->actingAs(User::factory()->create()->refresh());

    $page = visit('/alta')->resize(390, 900);

    $page->type('#if-name', 'Centro Vida');
    $page->type('#if-country_id', 'Argentina')->click('Argentina')->wait(1);
    $page->type('#if-province_id', 'Buenos')->click('Buenos Aires');

    pressChip($page, 'Salud');
    pressChip($page, 'Consultorio médico');
    pressVisibleContinue($page);

    // Step 3: a proposed service is picked and saved.
    pressChip($page, 'Consulta clínica');
    $page->assertSeeIn('.wizard-pills', 'Consulta clínica');
    pressVisibleContinue($page);

    expect(Business::query()->sole()->serviceNames())->toBe(['Consulta clínica']);

    // Back to the first step and another trade.
    $page->click('[data-testid="wizard-tab-2"]')->wait(1);
    pressChip($page, 'Gastronomía');
    pressChip($page, 'Restaurante');
    pressVisibleContinue($page);

    $page->assertDontSeeIn('.wizard-pills', 'Consulta clínica')
        ->assertSee('Reserva de mesa')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'wizard-trade-change-services');

    pressVisibleContinue($page);

    expect(Business::query()->sole()->serviceNames())->not->toContain('Consulta clínica');
});
