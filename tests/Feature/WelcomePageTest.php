<?php

declare(strict_types=1);

use App\Classes\Main\Plan;
use App\Models\Business;
use App\Models\ConversationMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the landing page loads successfully', function (): void {
    $this->get('/')->assertOk();
});

test('it shows the Atendia brand and hero', function (): void {
    // One register CTA wording for the whole page (landing audit, 2026-09-21).
    $this->get('/')
        ->assertSee('atendido por IA', false)
        ->assertSee('Crear mi asistente', false)
        ->assertDontSee('Empezar gratis', false);
});

test('it renders every marketing section', function (): void {
    $response = $this->get('/');

    // "clientes" is not here: real testimonials or no section at all.
    foreach (['funciones', 'como-funciona', 'casos', 'precios', 'preguntas'] as $id) {
        $response->assertSee('id="'.$id.'"', false);
    }
});

test('the navbar lists the sections in the order the page shows them', function (): void {
    // A menu item must always land further down than the one above it: a link
    // that scrolls the visitor back up reads as a broken page.
    $html = $this->get('/')->getContent();

    $sections = ['como-funciona', 'funciones', 'casos', 'precios', 'preguntas'];

    // The navbar renders before <main>, so the first hit of each anchor is its menu link.
    $positionOf = function (string $needle) use ($html): int {
        $at = mb_strpos($html, $needle);

        expect($at)->not->toBeFalse("Missing {$needle} on the landing page.");

        return (int) $at;
    };

    $menuOrder = collect($sections)
        ->sortBy(fn (string $id): int => $positionOf('href="#'.$id.'"'))
        ->values()
        ->all();

    $pageOrder = collect($sections)
        ->sortBy(fn (string $id): int => $positionOf('id="'.$id.'"'))
        ->values()
        ->all();

    expect($menuOrder)->toBe($pageOrder);
});

test('the navbar follows the reader: it marks the section and how far the page went', function (): void {
    $response = $this->get('/');

    foreach (['como-funciona', 'funciones', 'casos', 'precios', 'preguntas'] as $id) {
        $response->assertSee("active === '".$id."'", false);
    }

    $response->assertSee('nav-progress', false);
});

test('every feature tile carries a vignette, so none reads half empty', function (): void {
    // The brand tile shipped without one and sat visibly emptier than its row mates.
    $this->get('/')
        ->assertSee(__('landing.features.vignettes.brand_badge'))
        ->assertSee(__('landing.features.vignettes.brand_greeting'));
});

test('the hero opens with the loss, not the category', function (): void {
    // The headline promises the outcome (landing audit); the tagline stays
    // in the tab title. Two perks only: guarantees, not arguments.
    $this->get('/')
        ->assertSee(__('landing.hero.title_1'))
        ->assertSee(__('landing.hero.title_2'))
        ->assertSee('a las 3 de la mañana')
        ->assertSee(__('landing.hero.perk_try'))
        ->assertSee(__('landing.hero.perk_trial', ['days' => Plan::trial()->trialDays]))
        ->assertDontSee('Para cualquier rubro');
});

test('the faq closes with a human door when sales WhatsApp is set, and hides it when not', function (): void {
    config()->set('atendia.sales_whatsapp', '5492995529100');

    $this->get('/')
        ->assertSee(__('landing.faq.more_cta'))
        ->assertSee('wa.me/5492995529100', false);

    config()->set('atendia.sales_whatsapp', null);

    $this->get('/')->assertDontSee(__('landing.faq.more_cta'));
});

test('the faq is one hop away from the navbar and the footer', function (): void {
    $this->get('/')
        ->assertSee(__('landing.nav.faq'))
        ->assertSee('href="#preguntas"', false);
});

test('the faq answers the four fears and carries the FAQPage schema', function (): void {
    $this->get('/')
        ->assertSeeInOrder(['¿Sirve si vendo productos y no doy turnos?', '¿Qué pasa si me escriben en otro idioma?', '¿Puede decirle algo equivocado a mis clientes?'])
        ->assertSee('¿Se nota que es un bot?')
        ->assertSee('¿Pierdo el control de mi WhatsApp?')
        ->assertSee('¿Qué pasa con los datos de mis clientes?')
        ->assertSee('¿Cuándo se cobra y cómo cancelo?')
        ->assertSee('"@type":"FAQPage"', false)
        ->assertSee('acceptedAnswer', false);
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

test('the landing sells the any-language plus in the features and the live demo', function (): void {
    $this->get('/')
        ->assertSee(__('landing.features.always.title'))
        ->assertSee(__('landing.features.always.body'))
        // The hero phone demos the switch: an English exchange in the live pool.
        ->assertSee(__('landing.phone.b9'))
        ->assertSee(__('landing.phone.b10'))
        // ...and the switch shows in the SECOND question, not the ninth.
        ->assertSee(__('landing.phone.b3'));
});

test('the demo opens on a shop, where a bookings-only assistant cannot follow', function (): void {
    // Differentiation audit (2026-09-26): the rival's hero books a salon;
    // ours leads with commerce and keeps every service rubro behind it.
    $this->get('/')
        ->assertSee('Ferretería El Tornillo · Asistente')
        ->assertSeeInOrder(['data-demo-rubro="ferreteria"', 'data-demo-rubro="kiosco"', 'data-demo-rubro="clinica"'], false);
});

test('the features bento leads with the owner panel and vignettes of the real screens', function (): void {
    // Product-forward: the star tile mirrors the panel the owner buys, and
    // every feature keeps its copy beside a glimpse of its real screen.
    $this->get('/')
        ->assertSee('mini-window', false)
        ->assertSee(__('landing.features.control.title'))
        ->assertSee(__('landing.features.vignettes.stat_resolution'))
        ->assertSee(__('landing.features.vignettes.thread_2_text'))
        ->assertSee(__('landing.features.vignettes.schedule_badge'))
        ->assertSee(__('landing.features.vignettes.catalog_handoff'))
        ->assertSee(__('landing.features.vignettes.catalog_badge'));
});

test('pricing clarifies USD and only grounds a local amount when a rate is configured', function (): void {
    // No rate for the visitor's region: the USD note stands alone.
    $this->get('/')
        ->assertSee(__('landing.pricing.currency_note'))
        ->assertDontSee('valor de referencia');

    // A configured rate adds one reference line for the featured plan.
    config()->set('atendia.pricing_reference.es', ['symbol' => 'AR$', 'rate' => 1500]);

    $this->get('/')->assertSee(__('landing.pricing.local_reference', [
        'plan' => __('landing.pricing.negocio.name'),
        'amount' => 'AR$ 118.500',
    ]));
});

test('the pricing calculator argues with the visitor\'s own numbers, no invented stats', function (): void {
    $this->get('/')
        ->assertSee(__('landing.pricing.calculator.title'))
        ->assertSee(__('landing.pricing.calculator.slider_label'))
        ->assertSee('range-input', false)
        ->assertSee(__('landing.pricing.calculator.today_tag'))
        ->assertSee(__('landing.pricing.calculator.with_tag'))
        ->assertSee(__('landing.pricing.calculator.verdict_per_hour'))
        ->assertSee(__('landing.pricing.calculator.hour_value_label'))
        ->assertSee(__('landing.pricing.calculator.cta', ['days' => Plan::trial()->trialDays]))
        ->assertSee(__('landing.pricing.calculator.assumption', [
            'minutes' => config('atendia.calculator_minutes'),
        ]));
});

test('a sticky register bar follows the phone reader once the hero scrolls away', function (): void {
    $this->get('/')
        ->assertSee('mobile-cta', false)
        ->assertSee('lg:hidden', false);
});

test('the live tally speaks only from the floor up and never counts the demo', function (): void {
    config()->set('atendia.tally_floor', 3);

    // Two real replies plus two demo ones: still under the floor.
    ConversationMessage::factory()->out()->count(2)->create();
    $demo = Business::factory()->create(['billing_email' => Business::DEMO_EMAILS['clinica']]);
    ConversationMessage::factory()->out()->count(2)->create(['business_id' => $demo->id]);

    $this->get('/')->assertDontSee('conversaciones respondidas esta semana');

    ConversationMessage::factory()->out()->create();

    $this->get('/')->assertSee(trans_choice('landing.tally.line', 3, ['count' => 3]));
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
        ->assertSee(__('landing.pricing.negocio.name'))
        ->assertSee(__('landing.pricing.emprende.name'))
        ->assertSee(__('landing.pricing.premium.name'));
});

test('pricing charms: yearly toggle, incremental features and a trust line', function (): void {
    $this->get('/')
        // Both billing periods render server-side; Alpine only swaps visibility.
        ->assertSee(__('landing.pricing.billing_monthly'))
        ->assertSee(__('landing.pricing.billing_yearly_badge'))
        ->assertSee('$29', false)
        ->assertSee('$24', false)
        ->assertSee('$66', false)
        ->assertSee(__('landing.pricing.negocio.includes'))
        ->assertSee(__('landing.pricing.premium.includes'))
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
