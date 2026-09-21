<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->setLocale('es');
});

test('the loss headline and the faq accordion, in the flesh', function (): void {
    config()->set('atendia.sales_whatsapp', '5492995529100');

    $page = visit('/');

    $page->assertSee('Nunca más pierdas un cliente')
        ->screenshotElement('#top', 'landing-hero-loss');

    // Closed by default; the click unfolds ONE answer.
    $page->assertDontSee('nunca inventa')
        ->click('¿Puede decirle algo equivocado a mis clientes?')
        ->assertSee('nunca inventa')
        ->assertSee(__('landing.faq.more_cta'))
        ->screenshotElement('#preguntas', 'landing-faq-open');
});
