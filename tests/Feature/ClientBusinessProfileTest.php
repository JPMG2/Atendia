<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Currency;
use App\Models\SocialLink;
use App\Models\TaxCondition;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    $this->get(route('my-business'))
        ->assertSee(__('client.business.billing.natural_hint'))
        ->assertSee(__('client.business.recommended'))
        ->assertDontSee('required');
});

test('saying no to the premises question folds the address away', function (): void {
    Livewire::test('client.section-location')
        ->assertSee(__('client.business.location.address'))
        ->set('hasPremises', false)
        ->assertDontSee(__('client.business.location.address_placeholder'));
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
        ->assertSet('tax_id', 'J-11111111-1')
        ->set('currency_id', $currency->id)
        ->set('tax_id', 'J-12345678-9')
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
        ->set('tax_condition_id', $foreign->id)
        ->call('save')
        ->assertHasErrors(['tax_condition_id']);

    expect($business->fresh()->tax_condition_id)->toBeNull();
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
        ->assertSee(__('menu.profile_progress', ['percent' => 0]));
});

test('the hours mock shows split shifts and a closed sunday', function (): void {
    Livewire::test('client.section-hours')
        ->assertSee('16:00 – 20:00')
        ->assertSee(__('client.business.hours.closed'));
});

test('one click stamps monday onto the weekdays', function (): void {
    Livewire::test('client.section-hours')
        ->assertSet('shifts.5', ['09:00 – 18:00'])
        ->call('applyWeekdays')
        ->assertSet('shifts.5', ['09:00 – 13:00', '16:00 – 20:00'])
        ->assertSet('shifts.6', ['09:00 – 13:00'])
        ->assertSet('shifts.0', []);
});

test('the try-it-now overlay ships the simulated chat', function (): void {
    $this->actingAs(User::factory()->create()->refresh());

    $this->get(route('my-business'))
        ->assertSee(__('client.business.try.button'))
        ->assertSee(__('client.business.try.q2'))
        ->assertSeeHtml('wizard-phone-frame')
        ->assertSeeHtml('bp-try-overlay');
});
