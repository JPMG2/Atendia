<?php

declare(strict_types=1);

use App\Ai\Agents\AsistenteAtendia;
use Database\Seeders\DemoBusinessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->setLocale('es');
});

test('the visitor chats with the demo hardware store inside the hero phone', function (): void {
    Queue::fake();
    $this->seed(DemoBusinessSeeder::class);

    // Two entries per exchange: the grounding re-ask may consume a second.
    AsistenteAtendia::fake([
        'Buscando…', 'Sí, en el momento: la llave común cuesta $4.000 y la de auto $9.000.',
        'Buscando…', 'Sí, hacemos envíos en el barrio hasta las 23.',
    ]);

    $page = visit('/');

    // A chip is the visitor's first word; the reply lands as a jade bubble
    // and the remaining-questions nudge appears under the box.
    $page->assertSee(__('landing.demo.try_label'))
        ->click('¿Hacen copias de llaves?')
        ->assertSee('la llave común cuesta $4.000')
        ->assertSee(trans_choice('landing.demo.left', 3, ['count' => 3]))
        ->screenshotElement('.hero-phone-enter', 'hero-demo-chat')
        ->assertNoJavaScriptErrors();

    // Switching rubro hands the phone to the kiosk: new header, new chips,
    // fresh chat — and the budget keeps counting down across rubros.
    $page->click('Kiosco')
        ->assertSee('Kiosco El Faro · Asistente')
        ->assertDontSee('la llave común cuesta $4.000')
        ->click('¿Hacen envíos?')
        ->assertSee('hacemos envíos en el barrio')
        ->assertSee(trans_choice('landing.demo.left', 2, ['count' => 2]))
        ->screenshotElement('.hero-phone-enter', 'hero-demo-rubro-switch')
        ->assertNoJavaScriptErrors();
});

test('the spent budget turns into the register invite and the share door', function (): void {
    Queue::fake();
    $this->seed(DemoBusinessSeeder::class);
    config()->set('atendia.demo.session_cap', 1);

    AsistenteAtendia::fake(['Buscando…', 'Sí, en el momento: la llave común cuesta $4.000.']);

    $page = visit('/');

    // One budgeted reply: the goodbye, the rubro-named register CTA and the
    // WhatsApp share link take over the composer.
    $page->click('¿Hacen copias de llaves?')
        ->assertSee('la llave común cuesta $4.000')
        ->assertSee(__('landing.demo.cta_rubro', ['rubro' => 'ferretería']))
        ->assertSee(__('landing.demo.share'))
        ->screenshotElement('.hero-phone-enter', 'hero-demo-finished')
        ->assertVisible('[data-demo-share]');
});

test('a campaign deep link lands with its rubro already active', function (): void {
    Queue::fake();
    $this->seed(DemoBusinessSeeder::class);

    // The vet lives beyond the visible window: the deep link must rotate
    // its pill into view, greet as the vet and park the carousel.
    visit('/?rubro=veterinaria')
        ->assertSee('Veterinaria Patitas · Asistente')
        ->assertSee('Soy el asistente de Veterinaria Patitas')
        ->assertVisible('[data-demo-rubro="veterinaria"]');
});
