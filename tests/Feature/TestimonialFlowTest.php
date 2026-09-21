<?php

declare(strict_types=1);

use App\Enums\TestimonialStatus;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Testimonial;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function testimonialClient(?string $connectedAgo = null): User
{
    $user = User::factory()->create();
    $user->business()->associate(Business::factory()->create([
        'whatsapp_connected_at' => $connectedAgo === null ? null : now()->sub($connectedAgo),
    ]))->save();

    return $user;
}

test('the ask stays quiet before any milestone', function (): void {
    $this->actingAs(testimonialClient('5 days'));

    livewire('client.testimonial-card')
        ->assertDontSee(__('client.testimonial.title'));
});

test('a month connected brings the ask up once', function (): void {
    $this->actingAs(testimonialClient('31 days'));

    livewire('client.testimonial-card')
        ->assertSee(__('client.testimonial.title'))
        ->assertSee(__('client.testimonial.field_consent'));
});

test('a hundred conversations also cross the milestone', function (): void {
    $user = testimonialClient();
    Conversation::factory()->count(100)->create(['business_id' => $user->business_id]);

    $this->actingAs($user);

    livewire('client.testimonial-card')
        ->assertSee(__('client.testimonial.title'));
});

test('submitting stores the word as pending with the consent sealed', function (): void {
    $user = testimonialClient('31 days');
    $this->actingAs($user);

    livewire('client.testimonial-card')
        ->set('form.quote', 'Dejé de perder clientes por no contestar a tiempo.')
        ->set('form.rating', 5)
        ->set('form.consent', true)
        ->call('submit')
        ->assertDontSee(__('client.testimonial.title'));

    $testimonial = Testimonial::query()->sole();

    expect($testimonial->business_id)->toBe($user->business_id)
        ->and($testimonial->status)->toBe(TestimonialStatus::Pending)
        ->and($testimonial->rating)->toBe(5)
        ->and($testimonial->display_name)->toBe($user->business->name)
        ->and($testimonial->consent_given_at)->not->toBeNull();
});

test('without the checkbox the word stays internal: no consent recorded', function (): void {
    $this->actingAs(testimonialClient('31 days'));

    livewire('client.testimonial-card')
        ->set('form.quote', 'Muy conforme con el asistente.')
        ->call('submit');

    expect(Testimonial::query()->sole()->consent_given_at)->toBeNull();
});

test('waving the ask away is remembered and never nags again', function (): void {
    $user = testimonialClient('31 days');
    $this->actingAs($user);

    livewire('client.testimonial-card')
        ->call('dismiss')
        ->assertDontSee(__('client.testimonial.title'));

    expect(Testimonial::query()->sole()->status)->toBe(TestimonialStatus::Dismissed)
        ->and($user->business->refresh()->testimonialPromptDue())->toBeFalse();
});

test('the admin desk approves a consented word and it publishes', function (): void {
    $testimonial = Testimonial::factory()->create();

    livewire('admin.testimonials')
        ->assertSee($testimonial->display_name)
        ->call('approve', $testimonial->id);

    expect($testimonial->refresh()->status)->toBe(TestimonialStatus::Approved)
        ->and(Testimonial::published()->pluck('id')->all())->toBe([$testimonial->id]);
});

test('approval is refused when the owner never consented', function (): void {
    $testimonial = Testimonial::factory()->create(['consent_given_at' => null]);

    livewire('admin.testimonials')
        ->call('approve', $testimonial->id);

    expect($testimonial->refresh()->status)->toBe(TestimonialStatus::Pending);
});

test('a rejected word never reaches the landing', function (): void {
    $testimonial = Testimonial::factory()->approved()->create();

    livewire('admin.testimonials')
        ->call('reject', $testimonial->id);

    expect($testimonial->refresh()->status)->toBe(TestimonialStatus::Rejected)
        ->and(Testimonial::published())->toBeEmpty();
});

test('the moderation desk is locked to the admin panel', function (): void {
    $this->actingAs(testimonialClient());

    $this->get(route('admin.testimonials'))->assertForbidden();
});

test('with three published words the landing carousel speaks, with real names', function (): void {
    Testimonial::factory()->approved()->count(3)->sequence(
        ['display_name' => 'Ferretería El Tornillo', 'quote' => 'La agenda se llena sola.'],
        ['display_name' => 'Vivero La Semilla', 'quote' => 'Responde aunque yo esté regando.'],
        ['display_name' => 'Óptica Mirar Bien', 'quote' => 'Cero llamadas perdidas.'],
    )->create();

    $this->get('/')
        ->assertSee('id="clientes"', false)
        ->assertSee('Ferretería El Tornillo')
        ->assertSee('La agenda se llena sola.');
});

test('under three real words the whole social-proof block hides', function (): void {
    Testimonial::factory()->approved()->count(2)->create();

    $this->get('/')
        ->assertDontSee('id="clientes"', false)
        ->assertDontSee(__('landing.logos.title'));
});

test('the invented testimonials are gone for good', function (): void {
    $this->get('/')
        ->assertDontSee('Pastelería Mía')
        ->assertDontSee('Vendí 30% más')
        ->assertDontSee('Glow Spa');
});

test('the landing unfurls as a card when shared: open graph in place', function (): void {
    $this->get('/')
        ->assertSee('property="og:image"', false)
        ->assertSee('assets/og.png', false)
        ->assertSee('summary_large_image', false);
});

test('the footer only offers links that exist', function (): void {
    $this->get('/')
        ->assertSee('href="#funciones"', false)
        ->assertSee('href="#precios"', false)
        ->assertDontSee('>Blog<', false)
        ->assertDontSee('href="#"', false);
});
