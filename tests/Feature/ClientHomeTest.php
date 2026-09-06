<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Client home — the living mock-up (blessed 2026-09-06)
|--------------------------------------------------------------------------
| Two states, zero persistence: a fresh client meets the setup guide (the
| checklist IS the dashboard — KPIs at zero would only depress), and the
| active state shows the day-to-day numbers. These tests pin that contract.
*/

beforeEach(function (): void {
    app()->setLocale('es');
});

test('a fresh client lands on the setup guide, not on empty KPIs', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('Tu asistente está listo al 20%')
        ->assertDontSee(__('client.kpis.conversations'));
});

test('the account step arrives already ticked, endowed-progress style', function (): void {
    Livewire::test('client.home')
        ->assertSet('steps.account', true)
        ->assertSee(__('client.setup.steps.account.label'))
        ->assertSee(__('client.setup.progress', ['done' => 1, 'total' => 5]));
});

test('the WhatsApp step is the hero with its own primary call to action', function (): void {
    Livewire::test('client.home')
        ->assertSeeHtml('is-hero')
        ->assertSee(__('client.setup.steps.whatsapp.cta'));
});

test('the active state swaps the guide for the day-to-day numbers', function (): void {
    Livewire::test('client.home')
        ->call('switchTo', 'active')
        ->assertSee(__('client.kpis.conversations'))
        ->assertSee(__('client.recent.title'))
        ->assertDontSee(__('client.setup.sub'));
});

test('an unknown state falls back to the setup guide', function (): void {
    Livewire::test('client.home')
        ->call('switchTo', 'hacked')
        ->assertSet('mode', 'new');
});

test('empty widgets preview what will fill them instead of a naked zero', function (): void {
    Livewire::test('client.home')
        ->assertSee(__('client.previews.conversations_text'))
        ->assertSee(__('client.previews.metrics_text'));
});
