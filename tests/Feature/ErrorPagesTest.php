<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

test('a missing page renders the Atendia 404, not the Laravel one', function (): void {
    $response = $this->get('/this-page-does-not-exist');

    $response->assertNotFound()
        ->assertSee(__('errors.not_found.title'))
        ->assertSee('logo-mark.svg', false);
});

test('every error view is standalone and shows its own copy', function (string $view, string $key): void {
    $html = view("errors.{$view}")->render();

    expect($html)
        ->toContain(__("errors.{$key}.title"))
        ->toContain(__("errors.{$key}.message"))
        // Standalone golden rule: the page that reports a failure cannot lean
        // on Livewire, Alpine or the dashboard shell that may have caused it.
        ->not->toContain('livewire')
        ->not->toContain('x-data')
        ->not->toContain('app-shell');
})->with([
    ['403', 'forbidden'],
    ['404', 'not_found'],
    ['419', 'session_expired'],
    ['429', 'too_many_requests'],
    ['500', 'server_error'],
    ['503', 'maintenance'],
]);

test('the 419 page sends the user back to the login', function (): void {
    $html = view('errors.419')->render();

    expect($html)
        ->toContain(route('login'))
        ->toContain(__('errors.login_again'));
});

test('the login screen explains an expired session only when asked to', function (): void {
    $this->get('/login?expired=1')->assertOk()->assertSee(__('errors.expired_alert'));
    $this->get('/login')->assertOk()->assertDontSee(__('errors.expired_alert'));
});

test('every Livewire layout wires the failure handler', function (string $layout): void {
    $html = File::get(resource_path("views/layouts/{$layout}.blade.php"));

    expect($html)
        ->toContain('resources/js/livewire-failures.js')
        ->toContain('data-login-url')
        ->toContain('data-fail-title')
        ->toContain('data-graceful-failures');
})->with(['app', 'wizard']);

test('the failure handler covers both the 419 and the 5xx overlay', function (): void {
    $hook = File::get(resource_path('js/livewire-failures.js'));

    expect($hook)
        ->toContain('419')
        ->toContain('preventDefault')
        ->toContain('dialog?.retry');
});
