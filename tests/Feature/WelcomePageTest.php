<?php

declare(strict_types=1);

use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the landing page loads successfully', function (): void {
    $this->get('/')->assertOk();
});

test('it shows the Atendia brand and hero', function (): void {
    $this->get('/')
        ->assertSee('atendido por IA', false)
        ->assertSee('Crear mi asistente', false)
        ->assertSee('Empezar gratis', false);
});

test('it renders every marketing section', function (): void {
    $response = $this->get('/');

    foreach (['funciones', 'como-funciona', 'casos', 'precios', 'clientes'] as $id) {
        $response->assertSee('id="'.$id.'"', false);
    }
});

test('the hero phone wears the house bezel and the page enters animated', function (): void {
    $this->get('/')
        // Surface bezel + entrance sequence; the near-black frame that
        // swallowed the hero must never come back.
        ->assertSee('hero-phone-enter', false)
        ->assertSee('hero-enter-5', false)
        ->assertSee('phone-bubble-4', false)
        ->assertDontSee('background: var(--ink-900)', false);
});

test('the hero charms in the details: pulsing dot, sparking cta and clear anchors', function (): void {
    $this->get('/')
        ->assertSee('badge-dot-pulse', false)
        ->assertSee('cta-spark', false)
        // Both hero CTAs stand wired: register and the how-it-works anchor.
        ->assertSee(__('landing.hero.cta_primary'))
        ->assertSee(__('landing.hero.cta_secondary'))
        ->assertSee('href="#como-funciona"', false)
        ->assertSee(route('register'), false);
});

test('the landing sells the any-language plus in the hero and the features', function (): void {
    $this->get('/')
        ->assertSee(__('landing.hero.perk_lang'))
        ->assertSee(__('landing.features.always.title'))
        ->assertSee(__('landing.features.always.body'))
        // The hero phone demos the switch: an English exchange in the live pool.
        ->assertSee(__('landing.phone.b9'))
        ->assertSee(__('landing.phone.b10'));
});

test('the social proof waits for a real crowd before it speaks', function (): void {
    // Early days: no count is better than a sad count.
    $this->get('/')->assertDontSee('negocios ya atienden');

    Business::factory()->count(10)->create();

    $this->get('/')->assertSee(__('landing.hero.social_proof', ['count' => 10]));
});

test('the hero keeps breathing: typed headline, live clock and a chat pool', function (): void {
    $this->get('/')
        ->assertSee('data-hero-type', false)
        ->assertSee('data-hero-clock', false)
        ->assertSee('data-live-pool', false)
        // One live exchange straight from the translations.
        ->assertSee(__('landing.phone.b5'))
        ->assertSee('5G', false);
});

test('the pricing section emphasizes the business plan, Tailwind Plus style', function (): void {
    $response = $this->get('/');

    // One floating middle tier between two flush side panels.
    expect(substr_count($response->getContent(), 'pricing-tier-featured'))->toBe(1);

    $response
        ->assertSee('pricing-tier-left', false)
        ->assertSee('pricing-tier-right', false)
        ->assertSee(__('landing.pricing.featured_badge'))
        ->assertSee(__('landing.pricing.business.name'))
        ->assertSee(__('landing.pricing.starter.name'))
        ->assertSee(__('landing.pricing.pro.name'));
});

test('pricing charms: yearly toggle, incremental features and a trust line', function (): void {
    $this->get('/')
        // Both billing periods render server-side; Alpine only swaps visibility.
        ->assertSee(__('landing.pricing.billing_monthly'))
        ->assertSee(__('landing.pricing.billing_yearly_badge'))
        ->assertSee('$29', false)
        ->assertSee('$24', false)
        ->assertSee('$66', false)
        ->assertSee(__('landing.pricing.business.includes'))
        ->assertSee(__('landing.pricing.pro.includes'))
        ->assertSee(__('landing.pricing.trust'));
});

test('the yearly choice celebrates itself: bouncing badge and the money saved', function (): void {
    $this->get('/')
        ->assertSee('badge-bounce', false)
        // The struck-through monthly price sits beside the yearly one.
        ->assertSee('line-through', false)
        ->assertSee(__('landing.pricing.save_yearly', ['amount' => '$58']))
        ->assertSee(__('landing.pricing.save_yearly', ['amount' => '$158']));
});

test('the closing pitch shows the product still talking: a pocket phone mid-chat', function (): void {
    $this->get('/')
        ->assertSee('closing-phone', false)
        ->assertSee(__('landing.phone.b6'));
});

test('the Pro CTA opens a WhatsApp chat with sales, or falls back to register', function (): void {
    // No number configured yet: the CTA quietly points at register.
    $this->get('/')->assertDontSee('wa.me', false);

    config(['atendia.sales_whatsapp' => '5491100000000']);

    $this->get('/')
        ->assertSee('https://wa.me/5491100000000?text=', false)
        ->assertSee('target="_blank"', false);
});

test('it supports dark mode and is not a React prototype', function (): void {
    $this->get('/')
        // The theme toggle persists in localStorage under the project key.
        ->assertSee('atendia-theme', false)
        // Professional, not mediocre: no compiling React/Babel in the browser.
        ->assertDontSee('babel/standalone', false)
        ->assertDontSee('react-dom', false);
});

test('it renders icons as inline SVG, not through a JS CDN', function (): void {
    $this->get('/')
        ->assertSee('<svg', false)
        ->assertSee('class="lucide"', false)
        // No client-side icon library anymore: self-hosted inline SVG only.
        ->assertDontSee('data-lucide', false)
        ->assertDontSee('lucide.createIcons', false)
        ->assertDontSee('unpkg.com/lucide', false);
});
