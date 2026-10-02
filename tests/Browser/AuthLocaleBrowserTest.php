<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The access screens, in a regional variant
|--------------------------------------------------------------------------
| JSON translations have no fallback: Translator::get looks them up in the
| requested locale ONLY. A visitor on es_AR or es_VE read the English keys —
| "Register", "Already registered?" — on the very first screen of the
| product. These screens take their words from lang/<locale>/auth.php, which
| does fall back, and this walks the three variants to prove it.
*/

/** @return list<string> */
function authWords(): array
{
    return [
        __('auth.register.submit'),
        __('auth.register.already'),
        __('auth.fields.name'),
        __('auth.fields.password_confirmation'),
    ];
}

test('the register screen speaks Spanish in every variant', function (string $locale): void {
    app()->setLocale($locale);

    // The selector is how a real visitor picks a variant: it puts the locale
    // in the session, which is what the screens then read.
    $page = visit(route('locale.switch', $locale))->resize(1280, 1000);
    $page->navigate('/register');

    $page->assertNoJavaScriptErrors()
        ->assertDontSee('Register')
        ->assertDontSee('Already registered?')
        ->assertDontSee('Confirm Password');

    foreach (authWords() as $word) {
        $page->assertSee($word);
    }

    $page->screenshot(filename: 'auth-register-'.$locale);
})->with(['es', 'es_AR', 'es_VE']);

test('the login screen speaks Spanish in every variant', function (string $locale): void {
    app()->setLocale($locale);

    visit(route('locale.switch', $locale))->resize(1280, 1000)
        ->navigate('/login')
        ->assertNoJavaScriptErrors()
        ->assertDontSee('Remember me')
        ->assertDontSee('Log in')
        ->assertSee(__('auth.login.submit'))
        ->assertSee(__('auth.login.remember'))
        ->screenshot(filename: 'auth-login-'.$locale);
})->with(['es', 'es_AR', 'es_VE']);
