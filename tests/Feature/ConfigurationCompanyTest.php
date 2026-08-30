<?php

declare(strict_types=1);

use App\Dto\CompanyDto;
use App\Livewire\Forms\BaseForm;
use App\Livewire\Forms\Catalog\BaseCatalogForm;
use App\Livewire\Forms\Configuration\CompanyForm;
use App\Mail\NewCompany;
use App\Models\Business;
use App\Models\Company;
use App\Models\Country;
use App\Models\Menu;
use App\Models\Province;
use App\Models\Region;
use App\Models\SocialLink;
use App\Models\SocialNetwork;
use App\Models\TaxCondition;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

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

test('an admin sees the company screen with both steps', function (): void {
    $this->actingAs(companyAdmin())->get('/admin/company')
        ->assertOk()
        ->assertSee(__('company.title'))
        ->assertSee('<title>Compañía</title>', false)
        ->assertSee(__('company.steps.main.label'))
        ->assertSee(__('company.steps.commercial.label'));
});

test('the main step is the one that opens first', function (): void {
    // The stepper seeds Alpine with the active step, so the default is what the
    // markup says and not whichever panel happens to render first.
    $this->actingAs(companyAdmin())->get('/admin/company')
        ->assertSee("step: 'main'", false);
});

test('with no company saved the second step comes locked', function (): void {
    // The commercial data hangs off a record that does not exist yet, so the
    // screen walks the user through the main step first — and says what opens
    // the other one instead of just greying it out.
    $this->actingAs(companyAdmin());

    Livewire::test('configuration.company')
        ->assertSet('isRegistered', false)
        ->assertSeeHtml('unlocked: false')
        ->assertSee(__('company.steps.locked_hint'));
});

test('with the company already saved both steps are open', function (): void {
    // Nothing left to order once the record exists: the user jumps to whichever
    // step they came to edit.
    Company::factory()->create();

    $this->actingAs(companyAdmin());

    Livewire::test('configuration.company')
        ->assertSet('isRegistered', true)
        ->assertSeeHtml('unlocked: true');
});

test('the step the user is standing on survives a re-render', function (): void {
    // `isRegistered` seeds the stepper's x-data and is #[Locked] on purpose: if
    // that embedded JSON changed between renders, Livewire would rewrite the
    // attribute and Alpine would re-initialize the stepper, throwing the user
    // back to step one mid-typing.
    $this->actingAs(companyAdmin());

    $html = Livewire::test('configuration.company')
        ->set('form.data.legal_name', 'AtendIa SRL')
        ->html();

    expect($html)->toContain('unlocked: false');
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
    // and fire the same two queries a second time.
    Company::factory()->create();

    $form = new CompanyForm(new class extends Component {}, 'form');

    DB::enableQueryLog();
    $form->setup();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    // The company, its region, its province and its social links. Nothing else:
    // the country is not fetched because the only thing read off it is the id,
    // which lives on the province row.
    expect($queries)->toHaveCount(4);
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

/*
|--------------------------------------------------------------------------
| Front-end validation
|--------------------------------------------------------------------------
| Same recipe as the catalog masters: the global validate() from form-guard.js
| plus a per-component Alpine bag, so an incomplete form never costs a request.
*/

test('the company screen mounts the Alpine component that guards the save button', function (): void {
    $html = $this->actingAs(companyAdmin())->get('/admin/company')->getContent();

    expect($html)->toContain('x-data="companyForm"')     // the errors bag, one for the whole screen
        ->toContain('x-on:click="submit(step)"')         // save goes through validate() first, for THIS step
        ->toContain('errors.legal_name')                 // per-input Alpine error binding
        ->toContain('x-bind:aria-invalid');              // red border driven by that bag
});

test('every field the company screen marks as required is covered by the front validation', function (): void {
    // The asterisk and the rule have to say the same thing: a required field with
    // no rule does nothing when the user hits save, and shows no error either.
    $blade = file_get_contents(
        resource_path('views/components/configuration/⚡company.blade.php')
    );

    // Read the tags whole, so the check does not depend on the order the
    // attributes happen to be written in.
    preg_match_all('/<x-inputsform\.[\w.-]+\b(.*?)\/>/s', $blade, $tags);

    $required = [];

    foreach ($tags[1] as $attributes) {
        if (preg_match('/(^|\s)required(\s|$)/', $attributes) && preg_match('/\bname="(\w+)"/', $attributes, $match)) {
            $required[] = $match[1];
        }
    }

    expect($required)->toEqualCanonicalizing([
        'legal_name', 'country_id', 'province_id', 'region_id', 'address', 'tax_condition_id', 'tax_id',
    ]);

    foreach ($required as $field) {
        expect($blade)->toMatch("/\b{$field}: \[\s*'required'/")   // the rule exists
            ->toContain("alpine-error=\"{$field}\"");              // and the field shows it
    }
});

/*
|--------------------------------------------------------------------------
| Step two — contact and social links
|--------------------------------------------------------------------------
| Same record, its other columns, plus the rows of `social_links`. Step two
| never creates the company: it is locked until step one did.
*/

test('the social rows live on the server, with a blank one to start from', function (): void {
    // A row that only exists in Alpine cannot be saved. And the section is never
    // shown empty: there would be nowhere to type the first network.
    $this->actingAs(companyAdmin());

    Livewire::test('configuration.company')
        ->assertCount('form.social', 1)
        ->assertSet('form.social.0.social_network_id', null);
});

test('an existing company opens with its saved networks in order', function (): void {
    $company = Company::factory()->create();
    $instagram = SocialNetwork::factory()->create(['name' => 'Instagram']);
    $linkedin = SocialNetwork::factory()->create(['name' => 'LinkedIn']);

    SocialLink::factory()->for($company, 'linkable')->create([
        'social_network_id' => $linkedin->id, 'url' => 'https://linkedin.com/atendia', 'sort_order' => 1,
    ]);
    SocialLink::factory()->for($company, 'linkable')->create([
        'social_network_id' => $instagram->id, 'url' => 'https://instagram.com/atendia', 'sort_order' => 0,
    ]);

    $this->actingAs(companyAdmin());

    Livewire::test('configuration.company')
        ->assertCount('form.social', 2)
        ->assertSet('form.social.0.social_network_id', $instagram->id)
        ->assertSet('form.social.1.social_network_id', $linkedin->id);
});

test('rows are added and removed on the fly, and the last one always stays', function (): void {
    $this->actingAs(companyAdmin());

    $component = Livewire::test('configuration.company')
        ->call('addSocialRow', 0)
        ->assertCount('form.social', 2)
        ->call('removeSocialRow', 1)
        ->assertCount('form.social', 1);

    // Nothing to go back to if the last row could be deleted.
    $component->call('removeSocialRow', 0)->assertCount('form.social', 1);
});

test('removing a saved network deletes it right away, without waiting for save', function (): void {
    $company = Company::factory()->create();
    $link = SocialLink::factory()->for($company, 'linkable')->create();

    $this->actingAs(companyAdmin());

    Livewire::test('configuration.company')
        ->call('removeSocialRow', 0)
        ->assertDispatched('notify')
        // The last row never disappears: it comes back blank to start again.
        ->assertCount('form.social', 1)
        ->assertSet('form.social.0.id', null);

    $this->assertDatabaseMissing('social_links', ['id' => $link->id]);
});

test('deleting a network leaves a trail in the activity log', function (): void {
    // The removal is immediate and there is no recycle bin: without the log, a
    // link that vanishes leaves no way to know who took it out.
    $company = Company::factory()->create();
    $link = SocialLink::factory()->for($company, 'linkable')->create();
    $admin = companyAdmin();

    $this->actingAs($admin);

    Livewire::test('configuration.company')->call('removeSocialRow', 0);

    $activity = Activity::query()->where('subject_type', SocialLink::class)->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('deleted')
        ->and($activity->subject_id)->toBe($link->id)
        ->and($activity->causer_id)->toBe($admin->id)
        ->and($activity->log_name)->toBe('social');
});

test('removing an unsaved row touches nothing in the database', function (): void {
    // A row that was never saved has nothing to delete and nothing to warn about.
    $company = Company::factory()->create();
    $link = SocialLink::factory()->for($company, 'linkable')->create();

    $this->actingAs(companyAdmin());

    Livewire::test('configuration.company')
        ->call('addSocialRow', 0)
        ->call('removeSocialRow', 1)
        ->assertNotDispatched('notify');

    $this->assertDatabaseHas('social_links', ['id' => $link->id]);
});

test('a link of another owner cannot be deleted through the form state', function (): void {
    // `social` is public form state, so an id arriving from the front has to be
    // one of this company's own links.
    $company = Company::factory()->create();
    $business = Business::factory()->create();
    $foreign = SocialLink::factory()->for($business, 'linkable')->create();

    $this->actingAs(companyAdmin());

    Livewire::test('configuration.company')
        ->set('form.social.0.id', $foreign->id)
        ->call('removeSocialRow', 0);

    $this->assertDatabaseHas('social_links', ['id' => $foreign->id]);
});

test('the screen warns before deleting, through the system dialog', function (): void {
    // Golden rule: no native confirm anywhere. The warning is the only chance to
    // change your mind, since the delete does not wait for the save button.
    $blade = file_get_contents(
        resource_path('views/components/configuration/⚡company.blade.php')
    );

    expect($blade)->toContain('async removeSocial(index, saved) {')
        ->toContain('dialog.confirm({')
        ->toContain("type: 'danger'")
        // Only a saved row warns: an unsaved one has nothing to lose.
        ->toContain('if (saved && ! await dialog.confirm({');
});

test('every row keeps its own key, so removing one from the middle moves the right node', function (): void {
    $this->actingAs(companyAdmin());

    $component = Livewire::test('configuration.company')
        ->call('addSocialRow', 0)
        ->call('addSocialRow', 1);

    $keys = collect($component->get('form.social'))->pluck('key');

    expect($keys->unique())->toHaveCount(3);
});

test('saving the commercial step stores the contact and the networks', function (): void {
    $company = Company::factory()->create();
    $instagram = SocialNetwork::factory()->create(['name' => 'Instagram']);

    $this->actingAs(companyAdmin());

    Livewire::test('configuration.company')
        ->set('form.data.email', 'hola@atendia.app')
        ->set('form.data.phone', '+54 9 11 5555-1234')
        ->set('form.data.web', 'https://atendia.app')
        ->set('form.social.0.social_network_id', $instagram->id)
        ->set('form.social.0.url', 'https://instagram.com/atendia')
        ->call('saveCommercial')
        ->assertReturned(true)
        ->assertDispatched('notify');

    $this->assertDatabaseHas('companies', [
        'id' => $company->id,
        'email' => 'hola@atendia.app',
        'web' => 'https://atendia.app',
    ]);

    $this->assertDatabaseHas('social_links', [
        'linkable_type' => Company::class,
        'linkable_id' => $company->id,
        'social_network_id' => $instagram->id,
        'url' => 'https://instagram.com/atendia',
        'sort_order' => 0,
    ]);
});

test('saving the commercial step leaves the main columns alone', function (): void {
    // The mirror of the step one test: neither step may overwrite the other.
    $company = Company::factory()->create(['legal_name' => 'AtendIa SRL']);

    $this->actingAs(companyAdmin());

    Livewire::test('configuration.company')
        ->set('form.data.legal_name', 'Pisado desde el paso 2')
        ->set('form.data.email', 'hola@atendia.app')
        ->call('saveCommercial')
        ->assertReturned(true);

    expect($company->refresh()->legal_name)->toBe('AtendIa SRL')
        ->and($company->email)->toBe('hola@atendia.app');
});

test('a network removed from the screen is removed from the database', function (): void {
    // The row cannot survive in the table waiting for the next render.
    $company = Company::factory()->create();
    $link = SocialLink::factory()->for($company, 'linkable')->create();

    $this->actingAs(companyAdmin());

    Livewire::test('configuration.company')
        ->assertCount('form.social', 1)
        ->set('form.social.0.social_network_id', null)
        ->set('form.social.0.url', null)
        ->call('saveCommercial')
        ->assertReturned(true);

    $this->assertDatabaseMissing('social_links', ['id' => $link->id]);
});

test('editing a network edits its row instead of duplicating it', function (): void {
    // The network is the key: the table has one account per owner and network.
    $company = Company::factory()->create();
    $link = SocialLink::factory()->for($company, 'linkable')->create(['url' => 'https://instagram.com/viejo']);

    $this->actingAs(companyAdmin());

    Livewire::test('configuration.company')
        ->set('form.social.0.url', 'https://instagram.com/atendia')
        ->call('saveCommercial')
        ->assertReturned(true);

    expect(SocialLink::query()->count())->toBe(1)
        ->and($link->refresh()->url)->toBe('https://instagram.com/atendia');
});

test('the same network twice is a field error, not a database crash', function (): void {
    $company = Company::factory()->create();
    $instagram = SocialNetwork::factory()->create();

    $this->actingAs(companyAdmin());

    Livewire::test('configuration.company')
        ->set('form.social.0.social_network_id', $instagram->id)
        ->set('form.social.0.url', 'https://instagram.com/uno')
        ->call('addSocialRow', 0)
        ->set('form.social.1.social_network_id', $instagram->id)
        ->set('form.social.1.url', 'https://instagram.com/dos')
        ->call('saveCommercial')
        ->assertHasErrors(['social.0.social_network_id']);

    expect(SocialLink::query()->count())->toBe(0);
});

test('the blank starter row is not an error, but a half filled one is', function (): void {
    $company = Company::factory()->create();

    $this->actingAs(companyAdmin());

    // Untouched: it is simply not a network.
    Livewire::test('configuration.company')
        ->call('saveCommercial')
        ->assertHasNoErrors()
        ->assertReturned(true);

    // A link with no network picked has to say so, not be dropped in silence.
    Livewire::test('configuration.company')
        ->set('form.social.0.url', 'https://instagram.com/atendia')
        ->call('saveCommercial')
        ->assertHasErrors(['social.0.social_network_id']);
});

test('a malformed email or website bounces on the server', function (): void {
    Company::factory()->create();

    $this->actingAs(companyAdmin());

    Livewire::test('configuration.company')
        ->set('form.data.email', 'no-es-un-email')
        ->set('form.data.web', 'atendia')
        ->call('saveCommercial')
        ->assertHasErrors(['email', 'web']);
});

test('the commercial step never creates the company', function (): void {
    // It is locked until step one has run, and if the record is gone it says so
    // instead of inventing a half filled row.
    $this->actingAs(companyAdmin());

    Livewire::test('configuration.company')
        ->set('form.data.email', 'hola@atendia.app')
        ->call('saveCommercial')
        ->assertReturned(false)
        ->assertDispatched('notify');

    expect(Company::query()->count())->toBe(0);
});

test('discarding the commercial step brings back the contact and the networks', function (): void {
    $company = Company::factory()->create(['email' => 'hola@atendia.app']);
    SocialLink::factory()->for($company, 'linkable')->create(['url' => 'https://instagram.com/atendia']);

    $this->actingAs(companyAdmin());

    Livewire::test('configuration.company')
        ->set('form.data.email', 'escrito@y.arrepentido')
        ->set('form.social.0.url', 'https://instagram.com/otro')
        ->call('addSocialRow', 0)
        ->assertCount('form.social', 2)
        ->call('discardCommercial')
        ->assertSet('form.data.email', 'hola@atendia.app')
        ->assertCount('form.social', 1)
        ->assertSet('form.social.0.url', 'https://instagram.com/atendia');
});

test('discarding the commercial step does not touch the main one', function (): void {
    Company::factory()->create(['legal_name' => 'AtendIa SRL']);

    $this->actingAs(companyAdmin());

    Livewire::test('configuration.company')
        ->set('form.data.legal_name', 'Escrito en el paso 1')
        ->set('form.data.email', 'escrito@y.arrepentido')
        ->call('discardCommercial')
        ->assertSet('form.data.legal_name', 'Escrito en el paso 1')
        ->assertSet('form.data.email', Company::query()->sole()->email);
});

test('the commercial panel is rebuilt on discard, same as the main one', function (): void {
    Company::factory()->create();

    $this->actingAs(companyAdmin());

    Livewire::test('configuration.company')
        ->assertSeeHtml('wire:key="step-commercial-0"')
        ->call('discardCommercial')
        ->assertSeeHtml('wire:key="step-commercial-1"');
});

test('the front routes save and discard to the action of the step it is on', function (): void {
    $blade = file_get_contents(
        resource_path('views/components/configuration/⚡company.blade.php')
    );

    expect($blade)->toContain('await this.$wire.saveCommercial()')
        ->toContain('await this.$wire.discardCommercial()')
        // Only step one unlocks: saving step two must not move the user.
        ->toContain("if (step !== 'main') {");
});

/*
|--------------------------------------------------------------------------
| Step one — discarding
|--------------------------------------------------------------------------
*/

test('discarding the main step brings back what was saved', function (): void {
    $region = Region::factory()->create();
    $company = Company::factory()->create([
        'legal_name' => 'AtendIa SRL',
        'region_id' => $region->id,
    ]);

    $this->actingAs(companyAdmin());

    Livewire::test('configuration.company')
        ->set('form.data.legal_name', 'Escrito y arrepentido')
        ->set('form.data.tagline', 'Otra frase')
        ->call('discardMain')
        ->assertSet('form.data.legal_name', 'AtendIa SRL')
        ->assertSet('form.data.tagline', $company->tagline);
});

test('discarding brings back the cascade too, not just the region', function (): void {
    // Country and province are screen state derived from the region: if they were
    // not restored, the address would keep showing the discarded one.
    $region = Region::factory()->create();
    Company::factory()->create(['region_id' => $region->id]);

    $otherCountry = Country::factory()->create();

    $this->actingAs(companyAdmin());

    Livewire::test('configuration.company')
        ->set('form.data.country_id', $otherCountry->id)   // this also clears province and region
        ->assertSet('form.data.region_id', null)
        ->call('discardMain')
        ->assertSet('form.data.region_id', $region->id)
        ->assertSet('form.data.province_id', $region->province_id)
        ->assertSet('form.data.country_id', $region->province->country_id);
});

test('with no company saved, discarding leaves the main step blank', function (): void {
    $this->actingAs(companyAdmin());

    Livewire::test('configuration.company')
        ->set('form.data.legal_name', 'AtendIa SRL')
        ->set('form.data.tax_id', '30123456789')
        ->call('discardMain')
        ->assertSet('form.data.legal_name', '')
        ->assertSet('form.data.tax_id', '')
        ->assertSet('form.data.region_id', null);
});

test('discarding the main step does not touch what was typed in the second one', function (): void {
    // Same rule as saving: each step owns its own fields. Discarding step one
    // must not throw away an email the user just typed on step two.
    Company::factory()->create(['legal_name' => 'AtendIa SRL']);

    $this->actingAs(companyAdmin());

    Livewire::test('configuration.company')
        ->set('form.data.legal_name', 'Escrito y arrepentido')
        ->set('form.data.email', 'nuevo@atendia.app')
        ->call('discardMain')
        ->assertSet('form.data.legal_name', 'AtendIa SRL')
        ->assertSet('form.data.email', 'nuevo@atendia.app');
});

test('discarding clears the errors of the rejected attempt', function (): void {
    // A field left red by an attempt that was just discarded describes nothing
    // of what is on screen now.
    $this->actingAs(companyAdmin());

    Livewire::test('configuration.company')
        ->call('saveMain')
        ->assertHasErrors(['legal_name'])
        ->call('discardMain')
        ->assertHasNoErrors();
});

test('discarding rebuilds the panel instead of trusting the diff', function (): void {
    // The fields are deferred: what the user typed never reached the server, so
    // restoring a value the server already held renders identical HTML and
    // Livewire patches nothing — the typed text would survive on screen. The key
    // change is what forces the panel to come back from the server.
    Company::factory()->create();

    $this->actingAs(companyAdmin());

    $component = Livewire::test('configuration.company')
        ->assertSeeHtml('wire:key="step-main-0"')
        ->call('discardMain');

    $component->assertSeeHtml('wire:key="step-main-1"');
});

test('the front discards the step it is standing on and drops its own error bag', function (): void {
    $blade = file_get_contents(
        resource_path('views/components/configuration/⚡company.blade.php')
    );

    expect($blade)->toContain('x-on:click="discard(step)"')
        ->toContain('await this.$wire.discardMain()')
        // Step two has no discard yet: it must not fall through to step one's.
        ->toContain('async discard(step) {');
});

/*
|--------------------------------------------------------------------------
| Step one — saving
|--------------------------------------------------------------------------
| One record, two partial saves. Step one is an upsert and writes ONLY its own
| columns; step two has no action yet.
*/

/** Fills the screen with a valid main step and returns the component. */
function fillMainStep(?Region $region = null, ?TaxCondition $taxCondition = null)
{
    $region ??= Region::factory()->create();
    $taxCondition ??= TaxCondition::factory()->create();

    return Livewire::test('configuration.company')
        ->set('form.data.legal_name', 'AtendIa SRL')
        ->set('form.data.tagline', 'Tu negocio, atendido por IA')
        ->set('form.data.region_id', $region->id)
        ->set('form.data.address', 'Av. Siempre Viva 742')
        ->set('form.data.tax_condition_id', $taxCondition->id)
        ->set('form.data.tax_id', '30123456789')
        ->set('form.data.text_copyright', 'AtendIa. Todos los derechos reservados.');
}

test('the company form shares the generic base, not the catalog one', function (): void {
    // Company is a single record with no rail, no create/delete and no
    // CatalogWiring: it reuses the bottom floor (validate + tryAction) and none
    // of the catalog family's machinery.
    expect(is_subclass_of(CompanyForm::class, BaseForm::class))->toBeTrue()
        ->and(is_subclass_of(CompanyForm::class, BaseCatalogForm::class))->toBeFalse();
});

test('saving the main step creates the single company record', function (): void {
    $region = Region::factory()->create();
    $taxCondition = TaxCondition::factory()->create();

    $this->actingAs(companyAdmin());

    fillMainStep($region, $taxCondition)
        ->call('saveMain')
        ->assertReturned(true)
        ->assertDispatched('notify');

    // Every column of the step lands, the optional ones included: a field with
    // no rule would be dropped by validate() without a word.
    $this->assertDatabaseHas('companies', [
        'legal_name' => 'AtendIa SRL',
        'tagline' => 'Tu negocio, atendido por IA',
        'region_id' => $region->id,
        'address' => 'Av. Siempre Viva 742',
        'tax_condition_id' => $taxCondition->id,
        'tax_id' => '30123456789',
        'text_copyright' => 'AtendIa. Todos los derechos reservados.',
    ]);
});

test('the form keeps the id of the record it just created', function (): void {
    // Otherwise the next save would create a SECOND company in a table that has
    // exactly one row, forever.
    $this->actingAs(companyAdmin());

    $component = fillMainStep()->call('saveMain');

    $component->assertSet('form.recordId', Company::query()->sole()->id);

    $component->set('form.data.legal_name', 'AtendIa SA')->call('saveMain');

    expect(Company::query()->count())->toBe(1)
        ->and(Company::query()->sole()->legal_name)->toBe('AtendIa SA');
});

test('a save from a screen that mounted before the company existed still updates it', function (): void {
    // Two tabs open, or a double click: this form has recordId null but the row
    // is already there. Without the fallback lookup it would insert a second one.
    $this->actingAs(companyAdmin());

    $component = fillMainStep();

    Company::factory()->create(['legal_name' => 'Cargada en otra pestaña']);

    $component->call('saveMain');

    expect(Company::query()->count())->toBe(1)
        ->and(Company::query()->sole()->legal_name)->toBe('AtendIa SRL');
});

test('saving the main step leaves the commercial columns alone', function (): void {
    // The trap of two partial saves: validate() returns only the attributes that
    // have rules, so contact and socials are never overwritten with whatever the
    // DTO happened to be carrying.
    $company = Company::factory()->create(['email' => 'hola@atendia.app']);

    $this->actingAs(companyAdmin());

    Livewire::test('configuration.company')
        ->set('form.data.legal_name', 'AtendIa SRL')
        ->set('form.data.email', 'pisado@atendia.app')
        ->call('saveMain')
        ->assertReturned(true);

    expect($company->refresh()->email)->toBe('hola@atendia.app')
        ->and($company->legal_name)->toBe('AtendIa SRL');
});

test('an empty main step bounces on the server and saves nothing', function (): void {
    // The front guard is not the lock: a request that skips it still has to be
    // rejected, and every required field has to name itself.
    $this->actingAs(companyAdmin());

    Livewire::test('configuration.company')
        ->call('saveMain')
        ->assertHasErrors(['legal_name', 'region_id', 'address', 'tax_condition_id', 'tax_id'])
        ->assertReturned(null);

    expect(Company::query()->count())->toBe(0);
});

test('a region that does not exist bounces, which is what the front cannot check', function (): void {
    $this->actingAs(companyAdmin());

    fillMainStep()
        ->set('form.data.region_id', 999999)
        ->call('saveMain')
        ->assertHasErrors(['region_id']);

    expect(Company::query()->count())->toBe(0);
});

test('a tax id longer than its column bounces instead of crashing Postgres', function (): void {
    // The column is varchar(20); stringValid caps at 255, so without the extra
    // max the value would reach the database and blow up there.
    $this->actingAs(companyAdmin());

    fillMainStep()
        ->set('form.data.tax_id', str_repeat('9', 21))
        ->call('saveMain')
        ->assertHasErrors(['tax_id']);
});

test('the front saves the step it is standing on and only unlocks after the server said yes', function (): void {
    $blade = file_get_contents(
        resource_path('views/components/configuration/⚡company.blade.php')
    );

    expect($blade)->toContain('await this.$wire.saveMain()')
        ->toContain("this.\$dispatch('stepper-unlock')")
        // Step two has no action yet: it must not fall through to step one's.
        ->toContain("if (step !== 'main')");
});

test('the stepper opens the next step when it is unlocked', function (): void {
    $stepper = file_get_contents(resource_path('views/components/ui/stepper.blade.php'));

    expect($stepper)->toContain('x-on:stepper-unlock.window="unlock()"')
        ->toContain('unlock() {')
        // Saving again must not drag the user out of where they are standing.
        ->toContain('if (this.unlocked) { return }');
});

test('the actions close the screen at the bottom, with the same foot as the catalog masters', function (): void {
    // They used to sit above the steps: on a form this long the save button was
    // already off screen by the time the user finished filling it in.
    $blade = file_get_contents(
        resource_path('views/components/configuration/⚡company.blade.php')
    );

    expect($blade)->toContain('class="catalog-form-foot config-foot"')
        ->not->toContain('config-actions');

    // One foot for the whole screen, after both panels — not a bar per step.
    expect(substr_count($blade, 'catalog-form-foot'))->toBe(1);
    expect(strpos($blade, 'catalog-form-foot'))->toBeGreaterThan(strrpos($blade, '</x-ui.card>'));

    // ...and inside the stepper, which is what lets it read the current step.
    expect(strpos($blade, 'catalog-form-foot'))->toBeLessThan(strpos($blade, '</x-ui.stepper>'));
});

test('the save button hits the step the user is standing on, not the whole screen', function (): void {
    $blade = file_get_contents(
        resource_path('views/components/configuration/⚡company.blade.php')
    );

    // The step travels as an argument because it lives in the stepper's scope,
    // not in the screen's Alpine component.
    expect($blade)->toContain('x-on:click="submit(step)"')
        ->toContain('submit(step) {')
        ->toContain('const rules = this.rules[step] ?? {};');
});

test('the front rules are split per step so an error can never land on a hidden panel', function (): void {
    // A single bag would validate the other step's fields too: standing on step
    // one the button would look dead while the error was painted on a panel
    // nobody can see.
    $blade = file_get_contents(
        resource_path('views/components/configuration/⚡company.blade.php')
    );

    preg_match('/rules: \{(.*?)\n            \},/s', $blade, $bag);

    expect($bag[1])->toContain('main: {')
        ->toContain('commercial: {')
        // Every required field of the screen belongs to the main step's bag.
        ->toContain('legal_name:')
        ->toContain('tax_id:');
});

test('with no company saved the button says that saving is what opens the next step', function (): void {
    $this->actingAs(companyAdmin());

    // The server already renders the right label, so there is no flash of the
    // wrong one before Alpine boots.
    Livewire::test('configuration.company')
        ->assertSeeHtml('>'.__('company.save_continue').'</span>');
});

test('with the company already saved the button goes back to plain save', function (): void {
    Company::factory()->create();

    $this->actingAs(companyAdmin());

    // Both labels live in the x-text expression, so what is asserted is the text
    // the server actually painted inside the span.
    Livewire::test('configuration.company')
        ->assertSeeHtml('>'.__('company.save').'</span>')
        ->assertDontSeeHtml('>'.__('company.save_continue').'</span>');
});

test('changing step clears the errors of the step being left behind', function (): void {
    $blade = file_get_contents(
        resource_path('views/components/configuration/⚡company.blade.php')
    );

    $stepper = file_get_contents(resource_path('views/components/ui/stepper.blade.php'));

    // Otherwise you come back to step one and a field greets you in red from an
    // attempt you were no longer looking at.
    expect($blade)->toContain('x-on:step-changed="errors = {}"')
        ->and($stepper)->toContain("\$dispatch('step-changed'");
});

test('the company is welcomed the first time it saves an email', function (): void {
    Mail::fake();

    // Born without a contact: the address is a step two column, so a brand new
    // company has none until somebody fills it in.
    $company = Company::factory()->create(['email' => null]);

    $this->actingAs(companyAdmin());

    Livewire::test('configuration.company')
        ->set('form.data.email', 'hola@atendia.app')
        ->call('saveCommercial')
        ->assertReturned(true);

    // Queued, not sent: the mailable is ShouldQueue, so the save hands the mail
    // to the queue and returns instead of waiting on the SMTP server.
    Mail::assertQueued(NewCompany::class, function (NewCompany $mail) use ($company): bool {
        return $mail->hasTo('hola@atendia.app')
            && $mail->model->is($company);
    });
});

test('the welcome is not sent again on later saves', function (): void {
    Mail::fake();

    Company::factory()->create(['email' => 'hola@atendia.app']);

    $this->actingAs(companyAdmin());

    // A company that already had an address is not being welcomed, it is being
    // edited — and a change of address is not a welcome.
    Livewire::test('configuration.company')
        ->set('form.data.email', 'otro@atendia.app')
        ->call('saveCommercial')
        ->assertReturned(true);

    Mail::assertNothingOutgoing();
});

test('the main step never sends the welcome', function (): void {
    Mail::fake();

    $region = Region::factory()->create();
    $taxCondition = TaxCondition::factory()->create();

    $this->actingAs(companyAdmin());

    // The company is born here, but its address is not: there is nobody to
    // write to yet.
    Livewire::test('configuration.company')
        ->set('form.data.legal_name', 'AtendIa SRL')
        ->set('form.data.region_id', $region->id)
        ->set('form.data.address', 'Calle Falsa 123')
        ->set('form.data.tax_condition_id', $taxCondition->id)
        ->set('form.data.tax_id', '30-12345678-9')
        ->call('saveMain')
        ->assertReturned(true);

    Mail::assertNothingOutgoing();
});
