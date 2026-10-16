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
