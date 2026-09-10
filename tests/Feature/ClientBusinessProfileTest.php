<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\BusinessHour;
use App\Models\Currency;
use App\Models\SocialLink;
use App\Models\SocialNetwork;
use App\Models\TaxCondition;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| "Mi negocio" — the business profile
|--------------------------------------------------------------------------
| It pins the layout, the offer-never-require copy (GBP premises question,
| per-day shifts, natural-person billing), the panel lock, and the wired
| parts: the real profile meter and the billing card saving through Client.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('the profile screen sits behind the client panel lock', function (): void {
    $this->get(route('my-business'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create()->refresh());

    $this->get(route('my-business'))
        ->assertSuccessful()
        ->assertSee(__('client.business.title'))
        ->assertSee(__('client.business.identity.title'));
});

test('every optional field offers instead of requiring', function (): void {
    $this->actingAs(User::factory()->create()->refresh());

    // aria-required is what a required field renders; the plain word lives
    // in the front rule bags now, so it is no longer a usable marker.
    $this->get(route('my-business'))
        ->assertSee(__('client.business.billing.natural_hint'))
        ->assertSee(__('client.business.recommended'))
        ->assertDontSeeHtml('aria-required');
});

test('saying no to the premises question folds the address away', function (): void {
    $business = Business::factory()->create(['has_premises' => true]);
    $this->actingAs(User::factory()->create(['business_id' => $business->id])->refresh());

    Livewire::test('client.section-location')
        ->assertSee(__('client.business.location.address'))
        ->set('form.hasPremises', false)
        ->assertDontSee(__('client.business.location.address_placeholder'));
});

test('the location card hydrates from the business and saves through the identity slice', function (): void {
    $business = Business::factory()->create(['name' => 'Costuras Mary', 'address' => 'Av. Bolívar 12']);
    $this->actingAs(User::factory()->create(['business_id' => $business->id])->refresh());

    Livewire::test('client.section-location')
        ->assertSet('form.address', 'Av. Bolívar 12')
        ->set('form.hasPremises', true)
        ->assertSee($business->country->name)
        ->set('form.address', 'Calle Comercio 45')
        ->set('form.city', 'Valencia')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('notify');

    expect($business->fresh())
        ->address->toBe('Calle Comercio 45')
        ->city->toBe('Valencia')
        ->has_premises->toBeTrue()
        ->name->toBe('Costuras Mary');
});

test('an unanswered premises question lights neither button', function (): void {
    $business = Business::factory()->create(['has_premises' => null]);
    $this->actingAs(User::factory()->create(['business_id' => $business->id])->refresh());

    Livewire::test('client.section-location')
        ->assertSet('form.hasPremises', null)
        ->assertDontSeeHtml('is-active');
});

test('every section card carries its own save button', function (): void {
    $this->actingAs(User::factory()->create()->refresh());

    // Six sections, six saves: you commit the piece you touched, no more.
    $html = $this->get(route('my-business'))->getContent();

    expect(substr_count($html, __('client.business.actions.save')))->toBeGreaterThanOrEqual(6);
});

test('the setup guide leads here and discard leads back, both as SPA links', function (): void {
    $this->actingAs(User::factory()->create()->refresh());

    // The "complete your business" step is the door in…
    $this->get(route('dashboard'))->assertSeeHtml(route('my-business'));

    // …and cancelling is just walking back out to the dashboard.
    $this->get(route('my-business'))->assertSeeHtml(route('dashboard'));
});

test('a section deep link renders that single card, with the way back to the full profile', function (): void {
    $this->actingAs(User::factory()->create()->refresh());

    $this->get(route('my-business.horarios'))
        ->assertSuccessful()
        ->assertSee(__('client.business.hours.sub'))
        ->assertDontSee(__('client.business.identity.sub'))
        ->assertSeeHtml(route('my-business'));
});

test('the meter todos deep-link straight to their sections', function (): void {
    $this->actingAs(User::factory()->create()->refresh());

    // An empty profile misses every piece, so all four deep links show.
    $this->get(route('my-business'))
        ->assertSeeHtml(route('my-business.contacto'))
        ->assertSeeHtml(route('my-business.horarios'))
        ->assertSeeHtml(route('my-business.facturacion'))
        ->assertSeeHtml(route('my-business.redes'));
});

test('the menu offers every profile section as a child of my business', function (): void {
    $this->seed(MenuSeeder::class);
    $this->actingAs(User::factory()->create()->refresh());

    $this->get(route('dashboard'))
        ->assertSeeHtml(route('my-business.redes'))
        ->assertSee(__('client.business.social.title'));
});

test('the meter derives from the real profile and names what is missing', function (): void {
    $this->actingAs(User::factory()->create()->refresh());

    Livewire::test('client.business-profile')
        ->assertSee(__('client.business.meter.title', ['percent' => 0]))
        ->assertSee(__('client.business.meter.social_media'));
});

test('a complete profile swaps the meter for the celebration seal', function (): void {
    $business = Business::factory()->create([
        'whatsapp_number' => '+58 412 5551234',
        'fallback_whatsapp_number' => '+58 412 5555678',
        'email' => 'hola@esquina.com',
        'currency_id' => Currency::factory()->create()->id,
    ]);
    $business->hours()->create(['day_of_week' => 1, 'opens_at' => '09:00', 'closes_at' => '13:00']);
    SocialLink::factory()->for($business, 'linkable')->create();

    $this->actingAs(User::factory()->create(['business_id' => $business->id])->refresh());

    Livewire::test('client.business-profile')
        ->assertSee(__('client.business.meter.complete_title'))
        ->assertDontSee(__('client.business.meter.count', ['done' => 4, 'total' => 4]));
});

test('the billing card hydrates from the business and saves through the client class', function (): void {
    $currency = Currency::factory()->create();
    $business = Business::factory()->create(['tax_id' => 'J-11111111-1']);
    $this->actingAs(User::factory()->create(['business_id' => $business->id])->refresh());

    Livewire::test('client.section-billing')
        ->assertSet('form.tax_id', 'J-11111111-1')
        ->set('form.currency_id', $currency->id)
        ->set('form.tax_id', 'J-12345678-9')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('notify');

    expect($business->fresh())
        ->currency_id->toBe($currency->id)
        ->tax_id->toBe('J-12345678-9');
});

test('the billing card rejects a tax condition from another country', function (): void {
    $business = Business::factory()->create();
    $foreign = TaxCondition::factory()->create();
    $this->actingAs(User::factory()->create(['business_id' => $business->id])->refresh());

    Livewire::test('client.section-billing')
        ->set('form.tax_condition_id', $foreign->id)
        ->call('save')
        ->assertHasErrors(['tax_condition_id']);

    expect($business->fresh()->tax_condition_id)->toBeNull();
});

test('the social card hydrates from the business and saves through the client class', function (): void {
    $kept = SocialNetwork::factory()->create();
    $added = SocialNetwork::factory()->create();
    $business = Business::factory()->create();
    $link = SocialLink::factory()->for($business, 'linkable')->create([
        'social_network_id' => $kept->id,
        'url' => 'https://instagram.com/costurasmary',
    ]);
    $this->actingAs(User::factory()->create(['business_id' => $business->id])->refresh());

    Livewire::test('client.section-social')
        ->assertSet('form.social.0.url', 'https://instagram.com/costurasmary')
        ->call('addSocialRow', 0)
        ->set('form.social.1.social_network_id', $added->id)
        ->set('form.social.1.url', 'https://facebook.com/costurasmary')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('notify');

    expect($business->socialLinks()->orderBy('sort_order')->pluck('url')->all())
        ->toBe(['https://instagram.com/costurasmary', 'https://facebook.com/costurasmary']);

    expect($link->fresh()->sort_order)->toBe(0);
});

test('a removed social row leaves the table when the card is saved', function (): void {
    $business = Business::factory()->create();
    $link = SocialLink::factory()->for($business, 'linkable')->create();
    $this->actingAs(User::factory()->create(['business_id' => $business->id])->refresh());

    Livewire::test('client.section-social')
        ->call('removeSocialRow', 0)
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('notify');

    expect(SocialLink::query()->find($link->id))->toBeNull()
        ->and($business->socialLinks()->count())->toBe(0);
});

test('the social card rejects a half-filled row and a repeated network', function (): void {
    $network = SocialNetwork::factory()->create();
    $business = Business::factory()->create();
    $this->actingAs(User::factory()->create(['business_id' => $business->id])->refresh());

    Livewire::test('client.section-social')
        ->set('form.social.0.social_network_id', $network->id)
        ->call('save')
        ->assertHasErrors(['social.0.url']);

    Livewire::test('client.section-social')
        ->set('form.social.0.social_network_id', $network->id)
        ->set('form.social.0.url', 'https://instagram.com/uno')
        ->call('addSocialRow', 0)
        ->set('form.social.1.social_network_id', $network->id)
        ->set('form.social.1.url', 'https://instagram.com/dos')
        ->call('save')
        ->assertHasErrors(['social.0.social_network_id', 'social.1.social_network_id']);

    expect($business->socialLinks()->count())->toBe(0);
});

test('the hours card hydrates from the business and saves the week through the client class', function (): void {
    $business = Business::factory()->create();
    BusinessHour::factory()->for($business)->create([
        'day_of_week' => 1,
        'opens_at' => '09:00',
        'closes_at' => '13:00',
    ]);
    $this->actingAs(User::factory()->create(['business_id' => $business->id])->refresh());

    Livewire::test('client.section-hours')
        ->assertSet('form.week.1.0.opens_at', '09:00')
        ->call('addShift', 1)
        ->set('form.week.1.1.opens_at', '16:00')
        ->set('form.week.1.1.closes_at', '20:00')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('notify');

    expect($business->hours()->where('day_of_week', 1)->orderBy('opens_at')->pluck('closes_at')->all())
        ->toBe(['13:00:00', '20:00:00']);
});

test('the hours card rejects a shift that closes before it opens', function (): void {
    $business = Business::factory()->create();
    $this->actingAs(User::factory()->create(['business_id' => $business->id])->refresh());

    Livewire::test('client.section-hours')
        ->call('toggleDay', 1)
        ->set('form.week.1.0.opens_at', '18:00')
        ->set('form.week.1.0.closes_at', '09:00')
        ->call('save')
        ->assertHasErrors(['week.1.0.closes_at']);

    expect($business->hours()->count())->toBe(0);
});

test('the hours card rejects an hour that is not HH:MM', function (): void {
    $business = Business::factory()->create();
    $this->actingAs(User::factory()->create(['business_id' => $business->id])->refresh());

    Livewire::test('client.section-hours')
        ->call('toggleDay', 1)
        ->set('form.week.1.0.opens_at', '9.30')
        ->set('form.week.1.0.closes_at', '18:00')
        ->call('save')
        ->assertHasErrors(['week.1.0.opens_at']);

    expect($business->hours()->count())->toBe(0);
});

test('closing a day removes its shifts when the card is saved', function (): void {
    $business = Business::factory()->create();
    BusinessHour::factory()->for($business)->create([
        'day_of_week' => 2,
        'opens_at' => '09:00',
        'closes_at' => '18:00',
    ]);
    $this->actingAs(User::factory()->create(['business_id' => $business->id])->refresh());

    Livewire::test('client.section-hours')
        ->call('toggleDay', 2)
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('notify');

    expect($business->hours()->count())->toBe(0);
});

test('apply weekdays stamps monday onto tuesday to friday', function (): void {
    $business = Business::factory()->create();
    $this->actingAs(User::factory()->create(['business_id' => $business->id])->refresh());

    Livewire::test('client.section-hours')
        ->call('toggleDay', 1)
        ->assertSet('form.week.1.0', ['opens_at' => '09:00', 'closes_at' => '18:00'])
        ->set('form.week.1.0.closes_at', '17:00')
        ->call('applyWeekdays')
        ->assertSet('form.week.5.0.opens_at', '09:00')
        ->assertSet('form.week.5.0.closes_at', '17:00')
        ->assertSet('form.week.6', []);
});

test('the business name keeps its casing sacred and only sheds stray spaces', function (): void {
    // Meta's display-name rule: the name must match the brand EXACTLY,
    // so hygiene stops at whitespace — "KFC" and "YUCA" are the owner's call.
    $business = Business::factory()->create(['name' => '  KFC   Empanadas  ']);

    expect($business->name)->toBe('KFC Empanadas');

    $business->update(['name' => 'YUCA store']);

    expect($business->fresh()->name)->toBe('YUCA store');
});

test('the identity card hydrates from the business and saves through the identity slice', function (): void {
    $business = Business::factory()->create(['name' => 'Costuras Mary', 'whatsapp_number' => '+584140000000']);
    $this->actingAs(User::factory()->create(['business_id' => $business->id])->refresh());

    Livewire::test('client.section-identity')
        // The ai hand rides on the card; the fields stay the manual path.
        ->assertSee(__('client.business.identity.ai_title'))
        ->assertSee(__('client.business.identity.ai_action'))
        // The hint teaches WHY the casing is kept exactly as typed.
        ->assertSee(__('client.business.identity.name_hint'))
        ->assertSet('form.name', 'Costuras Mary')
        ->set('form.name', 'Costuras Mary e Hijas')
        ->set('form.description', 'Arreglos y confección a medida.')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('notify');

    expect($business->fresh())
        ->name->toBe('Costuras Mary e Hijas')
        ->description->toBe('Arreglos y confección a medida.')
        ->whatsapp_number->toBe('+584140000000');
});

test('a new logo lands on disk and the replaced file leaves it', function (): void {
    Storage::fake('public');
    $previous = UploadedFile::fake()->image('viejo.png')->store('logos', 'public');
    $business = Business::factory()->create(['logo_path' => $previous]);
    $this->actingAs(User::factory()->create(['business_id' => $business->id])->refresh());

    Livewire::test('client.section-identity')
        ->set('form.logo_file', UploadedFile::fake()->image('nuevo.png'))
        ->call('save')
        ->assertHasNoErrors();

    $stored = $business->fresh()->logo_path;

    expect($stored)->not->toBe($previous);
    Storage::disk('public')->assertExists($stored);
    Storage::disk('public')->assertMissing($previous);
});

test('the identity card rejects a nameless save and a non-image logo', function (): void {
    $business = Business::factory()->create();
    $this->actingAs(User::factory()->create(['business_id' => $business->id])->refresh());

    Livewire::test('client.section-identity')
        ->set('form.name', '')
        ->set('form.logo_file', UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf'))
        ->call('save')
        ->assertHasErrors(['name', 'logo_file']);
});

test('the contact card hydrates from the business and saves through the connection slice', function (): void {
    $business = Business::factory()->create([
        'email' => 'viejo@negocio.com',
        'whatsapp_number' => '+584140000000',
    ]);
    $this->actingAs(User::factory()->create(['business_id' => $business->id])->refresh());

    Livewire::test('client.section-contact')
        ->assertSet('form.email', 'viejo@negocio.com')
        ->set('form.email', 'hola@negocio.com')
        ->set('form.web', 'https://negocio.com')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('notify');

    expect($business->fresh())
        ->email->toBe('hola@negocio.com')
        ->web->toBe('https://negocio.com')
        ->whatsapp_number->toBe('+584140000000');
});

test('the contact card rejects a malformed email and website', function (): void {
    $business = Business::factory()->create();
    $this->actingAs(User::factory()->create(['business_id' => $business->id])->refresh());

    Livewire::test('client.section-contact')
        ->set('form.email', 'no-es-un-correo')
        ->set('form.web', 'negocio punto com')
        ->call('save')
        ->assertHasErrors(['email', 'web']);
});

test('the billing card suggests the registration country currency when none is saved', function (): void {
    $currency = Currency::factory()->create();
    $business = Business::factory()->create();
    $business->country->update(['currency_id' => $currency->id]);
    $this->actingAs(User::factory()->create(['business_id' => $business->id])->refresh());

    Livewire::test('client.section-billing')
        ->assertSet('form.currency_id', $currency->id);
});

test('the scalar cards ship their front validation hooks', function (): void {
    $this->actingAs(User::factory()->create()->refresh());

    // Four cards carry a rule bag; hours and social are row-based, so their
    // checks stay on the server, same criterion as the company screen.
    $html = $this->get(route('my-business'))->getContent();

    // The @script body ships as a Livewire asset, not inline HTML, so what
    // the page can vouch for is each bag's x-data and the fields wired to it.
    foreach (['clientIdentityForm', 'clientContactForm', 'clientLocationForm', 'clientBillingForm'] as $bag) {
        expect($html)->toContain('x-data="'.$bag.'"');
    }

    foreach (['errors.name', 'errors.email', 'errors.web', 'errors.tax_id'] as $wired) {
        expect($html)->toContain($wired);
    }
});

test('the unsaved-changes guard ships with its dialog copy', function (): void {
    $this->actingAs(User::factory()->create()->refresh());

    // @js() escapes non-ASCII, so the assertion sticks to the plain part.
    $this->get(route('my-business'))
        ->assertSee('Salir sin guardar', false)
        ->assertSee('livewire:navigate', false);
});

test('the live preview and the thank-you bubble ship their hooks and copy', function (): void {
    $this->actingAs(User::factory()->create()->refresh());

    // The JS repaints by these hooks; every section carries its slug so the
    // save click can pick its thank-you line.
    $this->get(route('my-business'))
        ->assertSeeHtml('data-bp-preview="name"')
        ->assertSeeHtml('data-bp-preview="message"')
        ->assertSeeHtml('data-bp-preview="thanks"')
        ->assertSeeHtml('data-section="horarios"')
        ->assertSee('cuándo estás abierto', false);
});

test('the sidebar carries the profile-strength pill from any client screen', function (): void {
    $this->seed(MenuSeeder::class);
    $this->actingAs(User::factory()->create()->refresh());

    $this->get(route('my-business'))
        ->assertSeeHtml('sidebar-progress')
        ->assertSee(__('menu.profile_progress', ['percent' => 0]))
        // Naming the next missing piece turns the meter into a to-do.
        ->assertSee(__('menu.profile_missing.personal_data'));
});

test('at 100% the sidebar meter bows out with the farewell note', function (): void {
    $this->seed(MenuSeeder::class);

    $business = Business::factory()->create([
        'whatsapp_number' => '+58 412 5551234',
        'fallback_whatsapp_number' => '+58 412 5555678',
        'email' => 'hola@esquina.com',
        'currency_id' => Currency::factory()->create()->id,
    ]);
    $business->hours()->create(['day_of_week' => 1, 'opens_at' => '09:00', 'closes_at' => '13:00']);
    SocialLink::factory()->for($business, 'linkable')->create();

    $this->actingAs(User::factory()->create(['business_id' => $business->id])->refresh());

    $this->get(route('my-business'))
        ->assertSee(__('menu.profile_done'))
        ->assertDontSeeHtml('sidebar-progress-head');
});

test('the try-it-now overlay ships the simulated chat', function (): void {
    $this->actingAs(User::factory()->create()->refresh());

    $this->get(route('my-business'))
        ->assertSee(__('client.business.try.button'))
        ->assertSee(__('client.business.try.q2'))
        ->assertSeeHtml('wizard-phone-frame')
        ->assertSeeHtml('bp-try-overlay');
});
