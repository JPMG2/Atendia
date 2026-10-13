<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the login explains an idle close in a painted warning, on a phone too', function (): void {
    app()->setLocale('es');

    $page = visit('/login?idle=1')->resize(390, 800);

    $page->assertSee('Cerramos tu sesión por inactividad')
        ->assertNoJavaScriptErrors();
    $page->wait(1)->screenshot(filename: 'login-idle-notice');

    // The alert variants were purged by Tailwind once and came out transparent:
    // measure the paint and the overflow, not the words.
    $paint = (string) $page->script(
        '(() => { const box = document.querySelector(".alert-warning"); return box ? getComputedStyle(box).backgroundColor : "missing"; })()'
    );
    $overflow = $page->script('document.documentElement.scrollWidth > document.documentElement.clientWidth');

    expect($paint)->not->toBe('missing')
        ->not->toBe('rgba(0, 0, 0, 0)')
        ->not->toBe('transparent')
        ->and($overflow)->toBeFalse();
});
