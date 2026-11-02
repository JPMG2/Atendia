<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The brand panel beside the login and register forms
|--------------------------------------------------------------------------
| Its copy lives in the language files: neutral for everyone, voseo only for
| Argentina. What a person sees is what these shots are for.
*/

test('the brand panel reads in the visitor\'s own voice', function (string $locale, string $title, string $other): void {
    // The selector's own door sets the locale; the same page then opens the login.
    $page = visit('/idioma/'.$locale)->resize(1280, 800);

    $page->navigate('/login')
        ->assertNoJavaScriptErrors()
        ->assertSee($title)
        ->assertDontSee($other)
        ->assertSee(__('auth.aside.points.fast'))
        ->screenshot(filename: 'guest-panel-'.$locale);
})->with([
    'neutral' => ['es', 'Automatiza tu WhatsApp y no pierdas más clientes.', 'Automatizá'],
    'argentina' => ['es_AR', 'Automatizá tu WhatsApp y no pierdas más clientes.', 'Automatiza tu'],
]);

test('the public deletion page reads whole on a phone and a desktop', function (int $width, int $height, bool $dark): void {
    $page = visit('/borrar-cuenta')->resize($width, $height);

    if ($dark) {
        $page->script("document.documentElement.classList.add('dark')");
    }

    $page->assertNoJavaScriptErrors()
        ->assertSee(__('deletion.heading'))
        ->assertSee(__('deletion.kept.payments'))
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->screenshot(fullPage: true, filename: 'account-deletion-'.$width.($dark ? '-dark' : ''));
})->with([
    'phone' => [390, 844, false],
    'phone dark' => [390, 844, true],
    'desktop' => [1280, 800, false],
]);
