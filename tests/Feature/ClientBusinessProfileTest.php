<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| "Mi negocio" — the living mock-up of the business profile
|--------------------------------------------------------------------------
| Zero persistence: it fixes the layout and the offer-never-require copy
| (GBP premises question, per-day shifts, natural-person billing) before
| any wiring lands. These tests pin that contract and the panel lock.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('the profile screen sits behind the client panel lock', function (): void {
    $this->get(route('my-business'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create());

    $this->get(route('my-business'))
        ->assertSuccessful()
        ->assertSee(__('client.business.title'))
        ->assertSee(__('client.business.identity.title'));
});

test('every optional field offers instead of requiring', function (): void {
    $this->actingAs(User::factory()->create());

    $this->get(route('my-business'))
        ->assertSee(__('client.business.billing.natural_hint'))
        ->assertSee(__('client.business.recommended'))
        ->assertDontSee('required');
});

test('saying no to the premises question folds the address away', function (): void {
    Livewire::test('client.business.section-location')
        ->assertSee(__('client.business.location.address'))
        ->set('hasPremises', false)
        ->assertDontSee(__('client.business.location.address_placeholder'));
});

test('every section card carries its own save button', function (): void {
    $this->actingAs(User::factory()->create());

    // Six sections, six saves: you commit the piece you touched, no more.
    $html = $this->get(route('my-business'))->getContent();

    expect(substr_count($html, __('client.business.actions.save')))->toBeGreaterThanOrEqual(6);
});

test('the setup guide leads here and discard leads back, both as SPA links', function (): void {
    $this->actingAs(User::factory()->create());

    // The "complete your business" step is the door in…
    $this->get(route('dashboard'))->assertSeeHtml(route('my-business'));

    // …and cancelling is just walking back out to the dashboard.
    $this->get(route('my-business'))->assertSeeHtml(route('dashboard'));
});

test('a section deep link renders that single card, with the way back to the full profile', function (): void {
    $this->actingAs(User::factory()->create());

    $this->get(route('my-business.horarios'))
        ->assertSuccessful()
        ->assertSee(__('client.business.hours.sub'))
        ->assertDontSee(__('client.business.identity.sub'))
        ->assertSeeHtml(route('my-business'));
});

test('the meter todos deep-link straight to their sections', function (): void {
    $this->actingAs(User::factory()->create());

    $this->get(route('my-business'))
        ->assertSeeHtml(route('my-business.identidad'))
        ->assertSeeHtml(route('my-business.horarios'))
        ->assertSeeHtml(route('my-business.facturacion'));
});

test('the menu offers every profile section as a child of my business', function (): void {
    $this->seed(MenuSeeder::class);
    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))
        ->assertSeeHtml(route('my-business.redes'))
        ->assertSee(__('client.business.social.title'));
});

test('a complete profile swaps the meter for the celebration seal', function (): void {
    Livewire::test('client.business-profile')
        ->assertSee(__('client.business.meter.todo_logo'))
        ->set('doneSteps', 5)
        ->assertSee(__('client.business.meter.complete_title'))
        ->assertDontSee(__('client.business.meter.todo_logo'));
});

test('the unsaved-changes guard ships with its dialog copy', function (): void {
    $this->actingAs(User::factory()->create());

    // @js() escapes non-ASCII, so the assertion sticks to the plain part.
    $this->get(route('my-business'))
        ->assertSee('Salir sin guardar', false)
        ->assertSee('livewire:navigate', false);
});

test('the live preview and the thank-you bubble ship their hooks and copy', function (): void {
    $this->actingAs(User::factory()->create());

    // The JS repaints by these hooks; every section carries its slug so the
    // save click can pick its thank-you line.
    $this->get(route('my-business'))
        ->assertSeeHtml('data-bp-preview="name"')
        ->assertSeeHtml('data-bp-preview="message"')
        ->assertSeeHtml('data-bp-preview="thanks"')
        ->assertSeeHtml('data-section="horarios"')
        ->assertSee('cuándo estás abierto', false);
});

test('the hours mock shows split shifts and a closed sunday', function (): void {
    Livewire::test('client.business.section-hours')
        ->assertSee('16:00 – 20:00')
        ->assertSee(__('client.business.hours.closed'));
});

test('one click stamps monday onto the weekdays', function (): void {
    Livewire::test('client.business.section-hours')
        ->assertSet('shifts.5', ['09:00 – 18:00'])
        ->call('applyWeekdays')
        ->assertSet('shifts.5', ['09:00 – 13:00', '16:00 – 20:00'])
        ->assertSet('shifts.6', ['09:00 – 13:00'])
        ->assertSet('shifts.0', []);
});

test('the try-it-now overlay ships the simulated chat', function (): void {
    $this->actingAs(User::factory()->create());

    $this->get(route('my-business'))
        ->assertSee(__('client.business.try.button'))
        ->assertSee(__('client.business.try.q2'))
        ->assertSeeHtml('wizard-phone-frame')
        ->assertSeeHtml('bp-try-overlay');
});
