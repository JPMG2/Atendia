<?php

declare(strict_types=1);

use App\Dto\CompanyDto;
use App\Livewire\Forms\Configuration\CompanyForm;
use App\Models\Company;
use App\Models\Country;
use App\Models\Menu;
use App\Models\Province;
use App\Models\Region;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/** An admin, which is what the whole screen is gated behind. */
function companyAdmin(): User
{
    $admin = User::factory()->create();
    $admin->syncRoles('admin');

    return $admin;
}

test('a guest is redirected to login from the company page', function (): void {
    $this->get('/admin/company')->assertRedirect(route('login'));
});

test('a client cannot reach the company page', function (): void {
    $client = User::factory()->create(); // the factory assigns the client role

    $this->actingAs($client)->get('/admin/company')->assertForbidden();
});

test('an admin sees the company screen with both tabs', function (): void {
    $this->actingAs(companyAdmin())->get('/admin/company')
        ->assertOk()
        ->assertSee(__('company.title'))
        ->assertSee('<title>Compañía</title>', false)
        ->assertSee(__('company.tabs.data'))
        ->assertSee(__('company.tabs.contact'));
});

test('the company data tab is the one that opens first', function (): void {
    // The tab bar seeds Alpine with the active tab, so the default is what the
    // markup says and not whichever panel happens to render first.
    $this->actingAs(companyAdmin())->get('/admin/company')
        ->assertSee("tab: 'data'", false);
});

test('every field of the company screen is rendered', function (): void {
    $response = $this->actingAs(companyAdmin())->get('/admin/company');

    // Each column of `companies` needs somewhere to be typed, or the screen
    // silently drops a field that the invoice header depends on.
    foreach ([
        'legal_name', 'tagline', 'tax_id', 'tax_condition_id', 'region_id',
        'address', 'logo_path_light', 'logo_path_dark', 'text_copyright',
        'email', 'phone', 'web',
    ] as $column) {
        $response->assertSee($column, false);
    }
});

test('the company screen uses no raw form control', function (): void {
    // Golden rule: fields come from <x-ui.*>/<x-inputsform.*>, which is what
    // guarantees the single focus ring, the theming and the error wiring.
    $blade = file_get_contents(
        resource_path('views/components/configuration/⚡company.blade.php')
    );

    expect($blade)->not->toMatch('/<(input|select|textarea)\b/');
});

test('the company option hangs from the settings item of the admin menu', function (): void {
    // It is not an area of its own: these are AtendIa's own data, so the option
    // lives inside Configuración and not next to it.
    $this->seed(MenuSeeder::class);

    $settings = Menu::query()->where('label_key', 'menu.admin_settings')->sole();

    $this->assertDatabaseHas('menus', [
        'panel' => 'admin',
        'parent_id' => $settings->id,
        'label_key' => 'menu.admin_company',
        'route_name' => 'admin.company',
        'icon' => 'building-2',
    ]);

    // The icon has to exist in the central registry or <x-icon> draws nothing.
    expect(config('icons.building-2'))->not->toBeNull();
});

test('mount initializes the DTO so wire:model can bind into the nested form object', function (): void {
    // The form's `data` starts null; `setup()` (run from mount) turns it into a real
    // CompanyDto. Without it, a `wire:model="form.data.legal_name"` update throws
    // "Cannot assign array to property ...CompanyDto" because Livewire cannot
    // recurse into null.
    $this->actingAs(companyAdmin());

    Livewire::test('configuration.company')
        ->assertSet('form.data.legal_name', '')
        ->set('form.data.legal_name', 'AtendIa SRL')
        ->assertSet('form.data.legal_name', 'AtendIa SRL')
        ->set('form.data.tax_id', '30-12345678-9')
        ->assertSet('form.data.tax_id', '30-12345678-9');
});

test('the form loads the single company record', function (): void {
    $company = Company::factory()->create(['legal_name' => 'AtendIa SRL']);

    $this->actingAs(companyAdmin());

    Livewire::test('configuration.company')
        ->assertSet('form.recordId', $company->id)
        ->assertSet('form.data.legal_name', 'AtendIa SRL')
        ->assertSet('form.data.tax_id', $company->tax_id)
        ->assertSet('form.data.email', $company->email)
        ->assertSet('form.data.region_id', $company->region_id)
        ->assertSet('form.data.tax_condition_id', $company->tax_condition_id);
});

test('country and province are derived from the region, since the table only stores the region', function (): void {
    // `companies` has no country_id/province_id: the address is asked for from the
    // general to the specific, so both combobox above the region are filled by
    // walking up region -> province -> country.
    $region = Region::factory()->create();
    $company = Company::factory()->create(['region_id' => $region->id]);

    $this->actingAs(companyAdmin());

    Livewire::test('configuration.company')
        ->assertSet('form.data.province_id', $region->province_id)
        ->assertSet('form.data.country_id', $region->province->country_id);

    // ...and they stay out of what gets persisted.
    expect(array_keys($company->refresh()->toArray()))
        ->not->toContain('country_id')
        ->not->toContain('province_id');
});

test('every column of the company screen is bound to the form', function (): void {
    // A field with no wire:model looks filled in and silently drops on save.
    $html = $this->actingAs(companyAdmin())->get('/admin/company')->getContent();

    // The modifier is free (`.live` on the fields that drive something else), so
    // the assertion is on the binding and not on how it is debounced.
    foreach ([
        'legal_name', 'tagline', 'country_id', 'province_id', 'region_id', 'address',
        'tax_condition_id', 'tax_id', 'text_copyright', 'email', 'phone', 'web',
    ] as $field) {
        expect($html)->toMatch('/wire:model(\.[\w.]+)?="form\.data\.'.$field.'"/');
    }
});

test('an empty text column is stored as null and not as an empty string', function (): void {
    // Otherwise half the table ends up "present but empty", which whereNull never finds.
    $dto = CompanyDto::fromArray([
        'legal_name' => '  AtendIa   SRL ',
        'tax_id' => ' 30-12345678-9 ',
        'address' => '   ',
        'web' => '',
    ]);

    $payload = $dto->toPayload();

    expect($payload['legal_name'])->toBe('AtendIa SRL')
        ->and($payload['tax_id'])->toBe('30-12345678-9')
        ->and($payload['address'])->toBeNull()
        ->and($payload['web'])->toBeNull();
});

test('the combobox ids arrive from the front as strings and reach the DTO as integers', function (): void {
    // The combobox posts its hidden value as "3"; under strict_types that is a
    // TypeError against a ?int property, and "no choice" arrives as '' — which is
    // null, never 0 (a 0 would sail past `exists` as a missing id).
    $dto = CompanyDto::fromArray([
        'region_id' => '3',
        'tax_condition_id' => '',
    ]);

    expect($dto->region_id)->toBe(3)
        ->and($dto->tax_condition_id)->toBeNull();
});

test('the province combobox only offers provinces of the chosen country', function (): void {
    $argentina = Country::factory()->create();
    $chile = Country::factory()->create();

    $mendoza = Province::factory()->create(['country_id' => $argentina->id, 'name' => 'Mendoza']);
    $valparaiso = Province::factory()->create(['country_id' => $chile->id, 'name' => 'Valparaiso']);

    $this->actingAs(companyAdmin());

    // The computed reads form.data.country_id, so the list narrows on the next
    // render without any hook reloading it.
    $html = Livewire::test('configuration.company')
        ->set('form.data.country_id', $argentina->id)
        ->html();

    expect($html)->toContain($mendoza->name)
        ->not->toContain($valparaiso->name);
});

test('with no country chosen the province combobox comes up empty', function (): void {
    // The cascade is closed: a province means nothing until the country above it
    // is picked, so the list stays empty instead of offering the whole catalog.
    $province = Province::factory()->create(['name' => 'Mendoza']);

    $html = $this->actingAs(companyAdmin())
        ->get('/admin/company')
        ->getContent();

    expect($html)->not->toContain($province->name);
});

test('the region combobox only offers regions of the chosen province', function (): void {
    $mendoza = Province::factory()->create(['name' => 'Mendoza']);
    $otherProvince = Province::factory()->create(['name' => 'Cordoba']);

    $cuyo = Region::factory()->create(['province_id' => $mendoza->id, 'name' => 'Cuyo']);
    $sierras = Region::factory()->create(['province_id' => $otherProvince->id, 'name' => 'Sierras']);

    $this->actingAs(companyAdmin());

    $html = Livewire::test('configuration.company')
        ->set('form.data.province_id', $mendoza->id)
        ->html();

    expect($html)->toContain($cuyo->name)
        ->not->toContain($sierras->name);
});

test('with no province chosen the region combobox comes up empty', function (): void {
    $region = Region::factory()->create(['name' => 'Cuyo']);

    $html = $this->actingAs(companyAdmin())
        ->get('/admin/company')
        ->getContent();

    expect($html)->not->toContain($region->name);
});

test('editing an existing company opens the cascade already filled in', function (): void {
    // The record only stores the region: if country and province were not derived
    // from it, both combobox above would come up empty and their own lists would
    // be empty too, so the screen would look like the address was never loaded.
    $region = Region::factory()->create(['name' => 'Cuyo']);
    Company::factory()->create(['region_id' => $region->id]);

    $this->actingAs(companyAdmin());

    $html = Livewire::test('configuration.company')
        ->assertSet('form.data.province_id', $region->province_id)
        ->assertSet('form.data.country_id', $region->province->country_id)
        ->html();

    expect($html)->toContain($region->province->name)
        ->toContain($region->name);
});

test('loading the company does not re-query the cascade it already eager loaded', function (): void {
    // `setup()` eager loads region.province and then reads province_id/country_id
    // off them. Asking for `$company->region()->...` instead would throw that away
    // and fire the same two queries a second time (6 instead of 3).
    Company::factory()->create();

    $form = new CompanyForm(new class extends Component {}, 'form');

    DB::enableQueryLog();
    $form->setup();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    // The company, its region and its province. Nothing else: the country is not
    // fetched because the only thing read off it is the id, which lives on the
    // province row.
    expect($queries)->toHaveCount(3);
});

test('changing the country clears the province and the region', function (): void {
    // The old province vanishes from the narrowed list but would survive in the
    // DTO, so what got saved would be a region belonging to another country.
    $region = Region::factory()->create();
    $otherCountry = Country::factory()->create();

    $this->actingAs(companyAdmin());

    Livewire::test('configuration.company')
        ->set('form.data.country_id', $region->province->country_id)
        ->set('form.data.province_id', $region->province_id)
        ->set('form.data.region_id', $region->id)
        ->set('form.data.country_id', $otherCountry->id)
        ->assertSet('form.data.country_id', $otherCountry->id)
        ->assertSet('form.data.province_id', null)
        ->assertSet('form.data.region_id', null);
});

test('changing the province clears the region but keeps the country', function (): void {
    // The new province still hangs from the same country, so there is nothing to
    // invalidate above it.
    $region = Region::factory()->create();
    $sibling = Province::factory()->create(['country_id' => $region->province->country_id]);

    $this->actingAs(companyAdmin());

    Livewire::test('configuration.company')
        ->set('form.data.country_id', $region->province->country_id)
        ->set('form.data.province_id', $region->province_id)
        ->set('form.data.region_id', $region->id)
        ->set('form.data.province_id', $sibling->id)
        ->assertSet('form.data.country_id', $region->province->country_id)
        ->assertSet('form.data.province_id', $sibling->id)
        ->assertSet('form.data.region_id', null);
});

test('loading a saved company does not clear its own cascade', function (): void {
    // setup() assigns country/province on the server, and server assignments do
    // not fire the updated hooks. If they did, opening the screen would wipe the
    // address it had just loaded.
    $region = Region::factory()->create();
    Company::factory()->create(['region_id' => $region->id]);

    $this->actingAs(companyAdmin());

    Livewire::test('configuration.company')
        ->assertSet('form.data.country_id', $region->province->country_id)
        ->assertSet('form.data.province_id', $region->province_id)
        ->assertSet('form.data.region_id', $region->id);
});

test('the reset itself does not cascade twice', function (): void {
    // Clearing province_id from updatedDataCountryId is a server assignment, so it
    // must not re-enter updatedDataProvinceId. The region is already null here, so
    // what this pins down is that a country change leaves the form usable rather
    // than looping.
    $region = Region::factory()->create();

    $this->actingAs(companyAdmin());

    Livewire::test('configuration.company')
        ->set('form.data.country_id', $region->province->country_id)
        ->set('form.data.province_id', $region->province_id)
        ->set('form.data.province_id', $region->province_id)
        ->assertSet('form.data.province_id', $region->province_id)
        ->assertSet('form.data.country_id', $region->province->country_id);
});
