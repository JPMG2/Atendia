<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\BusinessActivitySeeder;
use Database\Seeders\BusinessSectorSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(BusinessSectorSeeder::class);
    $this->seed(BusinessActivitySeeder::class);
    $this->actingAs(User::factory()->create());
});

/*
|--------------------------------------------------------------------------
| Wizard step 2 — the trade chips after the specialties and cuisines landed
|--------------------------------------------------------------------------
| Health went from 8 trades to 19 and gastronomy from 9 to 17. The chips are
| a flat cloud, so a real browser has to prove it still fits a phone.
*/

test('the trade chips of a grown sector list the new trades and fit a phone', function (string $sector, string $first, string $last, string $shot): void {
    $page = visit('/alta')->resize(390, 844);

    $page->script("[...document.querySelectorAll('.wizard-chip')].find(chip => chip.textContent.trim() === '{$sector}').click()");

    $page->assertSee($first)
        ->assertSee($last)
        ->assertNoJavaScriptErrors();

    $page->wait(1);

    $overflow = (int) $page->script('document.documentElement.scrollWidth - window.innerWidth');
    $page->script('document.querySelector("[data-activity-field]")?.scrollIntoView({ block: "center" })');
    $page->screenshot(filename: $shot);

    expect($overflow)->toBeLessThanOrEqual(0);
})->with([
    'health' => ['Salud', 'Pediatría', 'Cirugía plástica y estética', 'wizard-chips-health-phone'],
    'gastronomy' => ['Gastronomía', 'Arepera', 'Juguería', 'wizard-chips-gastronomy-phone'],
]);
