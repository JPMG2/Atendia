<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Testimonial;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('the milestone card, the moderation desk and the landing carousel, in the flesh', function (): void {
    // All rows are born BEFORE anyone logs in: the browser server shares
    // this connection, and a logged-in visit leaves its tenant on it.
    Testimonial::factory()->approved()->count(3)->sequence(
        ['display_name' => 'Ferretería El Tornillo', 'display_role' => 'Ferretería', 'quote' => 'La agenda se llena sola.', 'rating' => 5],
        ['display_name' => 'Vivero La Semilla', 'display_role' => 'Vivero', 'quote' => 'Responde aunque yo esté regando.', 'rating' => 4],
        ['display_name' => 'Óptica Mirar Bien', 'display_role' => 'Óptica', 'quote' => 'Cero llamadas perdidas.', 'rating' => 5],
    )->create();
    Testimonial::factory()->create(['display_name' => 'Kiosco La Esquina', 'quote' => 'Atiende de madrugada sin que yo esté.']);

    $owner = User::factory()->create();
    $owner->business()->associate(Business::factory()->create([
        'whatsapp_connected_at' => now()->subDays(35),
    ]))->save();

    // The ask, one month connected: quote, optional stars, explicit consent.
    $this->actingAs($owner);
    visit('/dashboard')
        ->assertSee(__('client.testimonial.title'))
        ->screenshotElement('.card.mb-4', 'testimonial-ask-card');

    // The desk: pending first, approve locked without consent.
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);
    visit('/admin/testimonios')
        ->assertSee('Kiosco La Esquina')
        ->screenshotElement('.card', 'testimonial-admin-desk');

    // Three consented, approved words wake the social-proof block — even
    // for a logged-in viewer: published words are public on purpose.
    visit('/')
        ->assertSee('Ferretería El Tornillo')
        ->screenshotElement('#clientes', 'landing-real-carousel');
});
