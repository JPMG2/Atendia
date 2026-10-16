<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The recovery screens, as a person sees them
|--------------------------------------------------------------------------
| The shots are for the eye: the same heading, field and button the login has.
*/

test('forgot-password looks like the login, in light and dark and on a phone', function (): void {
    $page = visit(route('password.request'))->resize(1280, 800);

    $page->assertNoJavaScriptErrors()
        ->assertSee(__('auth.forgot.heading'))
        ->assertPresent('.field-control')
        ->screenshot(filename: 'auth-forgot-light');

    $page->script('document.documentElement.classList.add("dark")');
    $page->screenshot(filename: 'auth-forgot-dark');

    $phone = visit(route('password.request'))->resize(390, 800);
    $phone->assertNoJavaScriptErrors()
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->screenshot(filename: 'auth-forgot-phone');
});

test('the rules check themselves off while a new password is typed, in reset and in register', function (string $screen): void {
    // Built here and not in the dataset: that runs before the app boots and would point at the real site.
    $url = $screen === 'reset' ? route('password.reset', ['token' => 'abc']) : route('register');
    $page = visit($url)->resize(1280, 900);

    $page->assertNoJavaScriptErrors()
        ->assertScript('document.querySelectorAll(".pw-rule.is-ok").length', 0)
        ->type('[name="password"]', 'abc')
        ->assertScript('document.querySelectorAll(".pw-rule.is-ok").length', 0)
        ->assertScript('document.querySelector(".pw-meter").dataset.strength', 'weak')
        ->type('[name="password"]', 'Abcdef1!')
        ->assertScript('document.querySelectorAll(".pw-rule.is-ok").length', 4)
        ->assertScript('document.querySelector(".pw-meter").dataset.strength', 'strong')
        ->screenshot(filename: 'auth-password-help-'.$screen);
})->with(['reset', 'register']);

test('reset, confirm and verify read the same way', function (): void {
    visit(route('password.reset', ['token' => 'abc']))->resize(1280, 800)
        ->assertNoJavaScriptErrors()
        ->assertSee(__('auth.reset.heading'))
        ->screenshot(filename: 'auth-reset');

    $this->actingAs(User::factory()->create());
    visit(route('password.confirm'))->resize(1280, 800)
        ->assertNoJavaScriptErrors()
        ->assertSee(__('auth.confirm.heading'))
        ->screenshot(filename: 'auth-confirm');

    $this->actingAs(User::factory()->unverified()->create());
    visit(route('verification.notice'))->resize(1280, 800)
        ->assertNoJavaScriptErrors()
        ->assertSee(__('auth.verify.heading'))
        ->screenshot(filename: 'auth-verify');
});
