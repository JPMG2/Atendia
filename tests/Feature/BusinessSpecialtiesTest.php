<?php

declare(strict_types=1);

use App\Actions\Business\SaveBusinessIdentity;
use App\Actions\Business\WriteBusinessBio;
use App\Ai\Agents\BusinessBioWriter;
use App\Models\Business;
use App\Models\BusinessActivity;
use App\Models\BusinessSector;
use App\Models\Province;
use App\Models\Specialty;
use App\Models\User;
use Database\Seeders\BusinessActivitySeeder;
use Database\Seeders\BusinessSectorSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// Saving a profile syncs it into the knowledge base: fake the queue or the
// indexing job would ride the network for embeddings.
beforeEach(function (): void {
    Queue::fake();
    app()->setLocale('es');
});

/**
 * A medical office offering two specialties, and a bakery that offers none.
 *
 * @return array{office: BusinessActivity, bakery: BusinessActivity, sector: BusinessSector}
 */
function specialtyTrades(): array
{
    $sector = BusinessSector::factory()->create(['code' => 'salud', 'name' => 'Salud']);
    $office = BusinessActivity::factory()->create(['code' => 'consultorio-medico', 'name' => 'Consultorio médico', 'business_sector_id' => $sector->id, 'specialty_label' => 'Especialidad']);
    $bakery = BusinessActivity::factory()->create(['code' => 'panaderia', 'name' => 'Panadería', 'business_sector_id' => $sector->id]);

    Specialty::factory()->for($office, 'activity')->create(['code' => 'medicina-interna', 'name' => 'Medicina interna', 'sort_order' => 1]);
    Specialty::factory()->for($office, 'activity')->create(['code' => 'neurologia', 'name' => 'Neurología', 'sort_order' => 2]);

    return ['office' => $office, 'bakery' => $bakery, 'sector' => $sector];
}

test('the seeder loads the 49 specialties and cuisines and names the field of their two trades', function (): void {
    $this->seed(BusinessSectorSeeder::class);
    $this->seed(BusinessActivitySeeder::class);
    $this->seed(SpecialtySeeder::class);

    expect(Specialty::query()->count())->toBe(49)
        ->and(Specialty::query()->whereHas('activity', fn ($q) => $q->where('code', 'consultorio-medico'))->count())->toBe(30)
        ->and(Specialty::query()->whereHas('activity', fn ($q) => $q->where('code', 'restaurante'))->count())->toBe(19)
        ->and(BusinessActivity::where('code', 'consultorio-medico')->sole()->specialty_label)->toBe('Especialidad')
        ->and(BusinessActivity::where('code', 'restaurante')->sole()->specialty_label)->toBe('Tipo de cocina');
});

test('a re-run duplicates nothing and respects what the master changed', function (): void {
    $this->seed(BusinessSectorSeeder::class);
    $this->seed(BusinessActivitySeeder::class);
    $this->seed(SpecialtySeeder::class);

    // She switched one off and renamed the field of a trade: neither is undone.
    Specialty::where('code', 'neurologia')->update(['is_active' => false]);
    BusinessActivity::where('code', 'restaurante')->update(['specialty_label' => 'Cocina']);

    $this->seed(SpecialtySeeder::class);

    expect(Specialty::query()->count())->toBe(49)
        ->and(Specialty::where('code', 'neurologia')->sole()->is_active)->toBeFalse()
        ->and(BusinessActivity::where('code', 'restaurante')->sole()->specialty_label)->toBe('Cocina');
});

test('a business keeps only the specialties its own activities offer', function (): void {
    $trades = specialtyTrades();
    Specialty::factory()->for($trades['bakery'], 'activity')->create(['code' => 'pan-de-masa-madre', 'name' => 'Masa madre']);
    Specialty::factory()->for($trades['office'], 'activity')->create(['code' => 'dormida', 'name' => 'Dormida', 'is_active' => false]);

    $business = Business::factory()->create();
    $business->syncActivities($trades['office']->id);

    // Another trade's code, an unknown one and a switched-off one are dropped.
    $business->syncSpecialties(['neurologia', 'pan-de-masa-madre', 'no-existe', 'dormida']);

    expect($business->specialtyCodes())->toBe(['neurologia']);
});

test('syncing replaces the previous marks instead of piling up, and an empty list clears them', function (): void {
    $trades = specialtyTrades();
    $business = Business::factory()->create();
    $business->syncActivities($trades['office']->id);

    $business->syncSpecialties(['medicina-interna', 'neurologia']);
    expect($business->specialtyCodes())->toBe(['medicina-interna', 'neurologia']);

    $business->syncSpecialties(['neurologia']);
    expect($business->specialtyCodes())->toBe(['neurologia']);

    $business->syncSpecialties([]);
    expect($business->specialtyCodes())->toBe([]);
});

test('the profile line uses the label of the primary activity', function (): void {
    $trades = specialtyTrades();
    $business = Business::factory()->create();
    $business->syncActivities($trades['office']->id);

    expect($business->specialtiesLine())->toBe('');

    $business->syncSpecialties(['medicina-interna', 'neurologia']);

    expect($business->specialtiesLine())->toBe('Especialidad: Medicina interna, Neurología');

    $trades['office']->update(['specialty_label' => null]);

    expect($business->fresh()->specialtiesLine())->toBe('Especialidades: Medicina interna, Neurología');
});

test('saving the identity touches the specialties only when the caller sends them', function (): void {
    $trades = specialtyTrades();
    $user = User::factory()->create();
    $business = Business::factory()->create();
    $user->business()->associate($business)->save();
    $business->syncActivities($trades['office']->id);
    $business->syncSpecialties(['neurologia']);

    // The mobile cards send their own fields and never this key.
    app(SaveBusinessIdentity::class)->handle($user->refresh(), ['name' => 'Clínica Vida']);
    expect($business->specialtyCodes())->toBe(['neurologia']);

    app(SaveBusinessIdentity::class)->handle($user->refresh(), ['name' => 'Clínica Vida', 'specialties' => ['medicina-interna']]);
    expect($business->specialtyCodes())->toBe(['medicina-interna']);
});

test('the wizard asks for the specialty only on a trade that offers some', function (): void {
    $trades = specialtyTrades();

    Livewire::test('business.step-business')
        ->call('choose', 'salud')
        ->call('chooseActivity', 'panaderia')
        ->assertDontSee('Medicina interna')
        ->call('chooseActivity', 'consultorio-medico')
        ->assertSee('Especialidad')
        ->assertSee('Medicina interna')
        ->assertSee('Neurología');
});

test('changing the trade clears what was marked for the previous one', function (): void {
    specialtyTrades();

    Livewire::test('business.step-business')
        ->call('choose', 'salud')
        ->call('chooseActivity', 'consultorio-medico')
        ->set('form.data.specialties', ['neurologia'])
        ->call('chooseActivity', 'panaderia')
        ->assertSet('form.data.specialties', []);
});

test('finishing the business step stores the specialties marked', function (): void {
    specialtyTrades();
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create()->refresh();
    $user->assignRole('client');
    $province = Province::factory()->create();

    Livewire::actingAs($user)
        ->test('business.step-business')
        ->set('form.data.name', 'Clínica Vida')
        ->set('form.data.country_id', $province->country_id)
        ->set('form.data.province_id', $province->id)
        ->call('choose', 'salud')
        ->call('chooseActivity', 'consultorio-medico')
        ->set('form.data.specialties', ['medicina-interna', 'neurologia'])
        ->call('finish')
        ->assertHasNoErrors()
        ->assertDispatched('wizard:step-completed', step: 2);

    expect(Business::sole()->specialtyCodes())->toBe(['medicina-interna', 'neurologia']);
});

test('a specialty of another trade is refused by the wizard', function (): void {
    $trades = specialtyTrades();
    Specialty::factory()->for($trades['bakery'], 'activity')->create(['code' => 'masa-madre', 'name' => 'Masa madre']);
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create()->refresh();
    $user->assignRole('client');
    $province = Province::factory()->create();

    Livewire::actingAs($user)
        ->test('business.step-business')
        ->set('form.data.name', 'Clínica Vida')
        ->set('form.data.country_id', $province->country_id)
        ->set('form.data.province_id', $province->id)
        ->call('choose', 'salud')
        ->call('chooseActivity', 'consultorio-medico')
        ->set('form.data.specialties', ['masa-madre'])
        ->call('finish')
        ->assertHasErrors();

    expect(Business::query()->count())->toBe(0);
});

test('the identity card lists and saves the specialties of the business activity', function (): void {
    $trades = specialtyTrades();
    $business = Business::factory()->create(['name' => 'Clínica Vida']);
    $business->syncActivities($trades['office']->id);
    $business->syncSpecialties(['neurologia']);
    $user = User::factory()->create();
    $user->business()->associate($business)->save();

    Livewire::actingAs($user)
        ->test('client.section-identity')
        ->assertSee('Especialidad')
        ->assertSee('Medicina interna')
        ->assertSet('form.specialties', ['neurologia'])
        ->set('form.specialties', ['medicina-interna', 'neurologia'])
        ->call('save')
        ->assertHasNoErrors();

    expect($business->specialtyCodes())->toBe(['medicina-interna', 'neurologia']);
});

test('the identity card carries no specialty field for a trade that offers none', function (): void {
    $trades = specialtyTrades();
    $business = Business::factory()->create(['name' => 'Panadería Sol']);
    $business->syncActivities($trades['bakery']->id);
    $user = User::factory()->create();
    $user->business()->associate($business)->save();

    Livewire::actingAs($user)
        ->test('client.section-identity')
        ->assertDontSee('Medicina interna')
        ->assertDontSeeHtml('name="specialties"');
});

test('the presentation prompt and the profile text carry the specialties', function (): void {
    $trades = specialtyTrades();
    $business = Business::factory()->create(['name' => 'Clínica Vida']);
    $business->syncActivities($trades['office']->id);
    $business->syncSpecialties(['neurologia']);

    $seen = null;
    BusinessBioWriter::fake(function (string $prompt) use (&$seen): array {
        $seen = $prompt;

        return ['description' => 'Clínica Vida atiende neurología.'];
    });

    app(WriteBusinessBio::class)->handle($business);

    expect($seen)->toContain('Especialidad: Neurología');
});

test('the identity card shows the primary activity and the others, and offers the rest', function (): void {
    $trades = specialtyTrades();
    $business = Business::factory()->create(['name' => 'Clínica Vida']);
    $business->syncActivities($trades['office']->id, [$trades['bakery']->id]);
    $user = User::factory()->create();
    $user->business()->associate($business)->save();

    Livewire::actingAs($user)
        ->test('client.section-identity')
        ->assertSet('form.activity', 'consultorio-medico')
        ->assertSet('form.other_activities', ['panaderia'])
        ->assertSee('Actividad principal')
        ->assertSee('Otras actividades');
});

test('changing the primary activity saves it and drops the specialties the new one does not offer', function (): void {
    $trades = specialtyTrades();
    $business = Business::factory()->create(['name' => 'Clínica Vida']);
    $business->syncActivities($trades['office']->id);
    $business->syncSpecialties(['neurologia']);
    $user = User::factory()->create();
    $user->business()->associate($business)->save();

    Livewire::actingAs($user)
        ->test('client.section-identity')
        ->set('form.activity', 'panaderia')
        // What the bakery does not offer leaves the screen at once, before saving.
        ->assertSet('form.specialties', [])
        ->call('save')
        ->assertHasNoErrors();

    expect($business->fresh()->primaryActivity()->code)->toBe('panaderia')
        ->and($business->specialtyCodes())->toBe([]);
});

test('the specialties offered follow the activities picked on screen, saved or not', function (): void {
    $trades = specialtyTrades();
    $business = Business::factory()->create(['name' => 'Panadería Sol']);
    $business->syncActivities($trades['bakery']->id);
    $user = User::factory()->create();
    $user->business()->associate($business)->save();

    Livewire::actingAs($user)
        ->test('client.section-identity')
        ->assertDontSee('Medicina interna')
        ->set('form.other_activities', ['consultorio-medico'])
        ->assertSee('Medicina interna');
});

test('the others are replaced when sent and kept when not, and the primary is never one of them', function (): void {
    $trades = specialtyTrades();
    $third = BusinessActivity::factory()->create(['code' => 'otra', 'business_sector_id' => $trades['sector']->id]);
    $user = User::factory()->create();
    $business = Business::factory()->create();
    $user->business()->associate($business)->save();

    $save = fn (array $data) => app(SaveBusinessIdentity::class)->handle($user->refresh(), ['name' => 'Clínica Vida', ...$data]);

    $save(['activity' => 'consultorio-medico', 'other_activities' => ['panaderia', 'consultorio-medico']]);
    expect($business->secondaryActivityCodes())->toBe(['panaderia']);

    // The wizard sends the primary and no others: what was declared stays.
    $save(['activity' => 'consultorio-medico']);
    expect($business->secondaryActivityCodes())->toBe(['panaderia']);

    $save(['activity' => 'consultorio-medico', 'other_activities' => [$third->code]]);
    expect($business->secondaryActivityCodes())->toBe(['otra']);
});

test('a card that sends no activity leaves the activities and the specialties as they were', function (): void {
    $trades = specialtyTrades();
    $user = User::factory()->create();
    $business = Business::factory()->create();
    $user->business()->associate($business)->save();
    $business->syncActivities($trades['office']->id, [$trades['bakery']->id]);
    $business->syncSpecialties(['neurologia']);

    app(SaveBusinessIdentity::class)->handle($user->refresh(), ['name' => 'Nombre nuevo']);

    expect($business->fresh()->primaryActivity()->code)->toBe('consultorio-medico')
        ->and($business->secondaryActivityCodes())->toBe(['panaderia'])
        ->and($business->specialtyCodes())->toBe(['neurologia']);
});

test('an unknown or switched-off activity is refused by the identity card', function (): void {
    $trades = specialtyTrades();
    $trades['bakery']->update(['is_active' => false]);
    $business = Business::factory()->create();
    $business->syncActivities($trades['office']->id);
    $user = User::factory()->create();
    $user->business()->associate($business)->save();

    Livewire::actingAs($user)
        ->test('client.section-identity')
        ->set('form.activity', 'panaderia')
        ->call('save')
        ->assertHasErrors('activity');

    expect($business->fresh()->primaryActivity()->code)->toBe('consultorio-medico');
});
