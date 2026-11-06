<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\BusinessActivity;
use App\Models\User;
use Database\Seeders\BusinessActivitySeeder;
use Database\Seeders\BusinessSectorSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The specialty field — where it is chosen, in a real browser
|--------------------------------------------------------------------------
| The chips live inside the field and the list entangles with Livewire, so
| only a browser proves the marks reach the component's state and that the
| box still fits a phone once several are on.
*/

beforeEach(function (): void {
    Queue::fake();
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
    $this->seed(BusinessSectorSeeder::class);
    $this->seed(BusinessActivitySeeder::class);
    $this->seed(SpecialtySeeder::class);
});

/** The marked codes as the Livewire component holds them, read from the page itself. */
function markedCodes(object $page, string $path): array
{
    $json = $page->script("(() => { try { const found = window.Livewire.all().map((c) => c.\$wire.get('{$path}')).find((v) => Array.isArray(v)); return JSON.stringify(found ?? null); } catch (e) { return 'null'; } })()");

    return json_decode((string) $json, true) ?? [];
}

test('in the wizard the field appears for a medical office, takes several marks and removes one', function (): void {
    $this->actingAs(User::factory()->create()->refresh());

    $page = visit('/alta')->resize(390, 844);

    $page->script("[...document.querySelectorAll('.wizard-chip')].find(chip => chip.textContent.trim() === 'Salud').click()");
    $page->assertSee('Consultorio médico');
    $page->assertDontSee('Especialidad · Opcional');

    $page->script("[...document.querySelectorAll('.wizard-chip')].find(chip => chip.textContent.trim() === 'Consultorio médico').click()");
    $page->assertSee('Especialidad · Opcional');

    $page->type('#if-specialties', 'neuro')->click('Neurología')->assertSeeIn('.combo-chip', 'Neurología');
    $page->type('#if-specialties', 'interna')->click('Medicina interna');

    expect(markedCodes($page, 'form.data.specialties'))->toBe(['neurologia', 'medicina-interna']);

    $page->click('[aria-label="Quitar Neurología"]');

    expect(markedCodes($page, 'form.data.specialties'))->toBe(['medicina-interna']);

    $overflow = (int) $page->script('document.documentElement.scrollWidth - window.innerWidth');
    $page->script('document.getElementById("if-specialties")?.scrollIntoView({ block: "center" })');
    $page->screenshot(filename: 'specialty-wizard-phone');

    expect($overflow)->toBeLessThanOrEqual(0);
});

test('a restaurant is asked for its cuisine instead, and a bakery for nothing', function (): void {
    $this->actingAs(User::factory()->create()->refresh());

    $page = visit('/alta')->resize(390, 844);

    $page->script("[...document.querySelectorAll('.wizard-chip')].find(chip => chip.textContent.trim() === 'Gastronomía').click()");
    $page->assertSee('Panadería');
    $page->script("[...document.querySelectorAll('.wizard-chip')].find(chip => chip.textContent.trim() === 'Panadería').click()");
    $page->assertDontSee('Tipo de cocina');

    $page->script("[...document.querySelectorAll('.wizard-chip')].find(chip => chip.textContent.trim() === 'Restaurante').click()");
    $page->assertSee('Tipo de cocina · Opcional');
});

test('in Mi negocio the identity card shows the marks, adds one and saves', function (): void {
    $business = Business::factory()->create(['name' => 'Clínica Vida']);
    $business->syncActivities(BusinessActivity::where('code', 'consultorio-medico')->value('id'));
    $business->syncSpecialties(['neurologia']);
    $user = User::factory()->create(['name' => 'María González']);
    $user->business()->associate($business)->save();
    $this->actingAs($user->refresh());

    $page = visit(route('my-business.identidad'))->resize(1280, 900);

    $page->assertNoJavaScriptErrors()
        ->assertSee('Especialidad')
        ->assertSeeIn('.combo-chip', 'Neurología');

    $page->type('#if-specialties', 'geriatr')->click('Geriatría');
    $page->screenshot(filename: 'specialty-identity-desktop');
    $page->click('Guardar')->wait(2);

    expect($business->specialtyCodes())->toBe(['geriatria', 'neurologia']);
});
