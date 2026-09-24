<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->setLocale('es');
    // The wizard writes real data, so it sits behind the client-panel lock (2026-09-03).
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->actingAs(User::factory()->create());
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
        ->fill('name', 'Clínica Vida')
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
    $page->fill('name', 'C');
    usleep(400000);
    $page->fill('name', 'Clínica Vida');

    $page->assertVisible('.wizard-phone .msg.out');

    // Give the stray timers (≤ ~2s when the bug was alive) time to fire.
    usleep(2500000);

    expect((int) $page->script('document.querySelectorAll("[data-phone] .msg").length'))->toBe(2);
});
