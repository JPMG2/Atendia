<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->setLocale('es');
});

/*
|--------------------------------------------------------------------------
| Client onboarding wizard — the phone preview, in a real browser
|--------------------------------------------------------------------------
| The preview is painted by the component's script (typing dots, then the
| bubble), so only a browser proves it moves. Born from a regression: the
| script used $wire.on instead of $wire.$on and the phone stayed dead.
*/

test('typing the business name animates the assistant into the phone', function (): void {
    $page = visit('/alta');

    $page->assertSee(__('wizard.fields.business_name'))
        ->fill('business_name', 'Clínica Vida')
        ->assertNoJavaScriptErrors();

    // These retry until the choreography finished: the client bubble, the
    // typing dots, then the assistant introducing itself by name.
    $page->assertVisible('.wizard-phone .msg.out')
        ->assertSee('Soy el asistente de')
        ->assertSee('Clínica Vida');
});

test('fast typing never duplicates the assistant reply', function (): void {
    $page = visit('/alta');

    // Two live updates racing: the first schedules the animation, the second
    // repaints silently mid-flight. The stray timers used to append the stale
    // first-letter reply behind the real one.
    $page->fill('business_name', 'C');
    usleep(400000);
    $page->fill('business_name', 'Clínica Vida');

    $page->assertVisible('.wizard-phone .msg.out');

    // Give the stray timers (≤ ~2s when the bug was alive) time to fire.
    usleep(2500000);

    expect((int) $page->script('document.querySelectorAll("[data-phone] .msg").length'))->toBe(2);
});

test('scanning on step five drops the connected bubble into the chat', function (): void {
    $page = visit('/alta');

    $page->fill('business_name', 'Clínica Vida');
    $page->assertVisible('.wizard-phone .msg.out');

    $page->click('@wizard-tab-5')
        ->click(__('wizard.whatsapp.scanned'))
        ->assertNoJavaScriptErrors();

    $page->assertSee('Conectado a tu WhatsApp')
        ->assertSee(__('wizard.done.heading'));
});
