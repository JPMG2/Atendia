<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The palette, in a real browser
|--------------------------------------------------------------------------
| A keyboard-first surface cannot be proven by a server render: the shortcut,
| the arrows and the teleported panel only exist once Alpine runs.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);

    $this->business = Business::factory()->create(['name' => 'Clínica Vida']);
    $user = User::factory()->create(['name' => 'María González']);
    $user->business()->associate($this->business)->save();
    $this->actingAs($user);

    Service::factory()->for($this->business)->create(['name' => 'Corte de pelo']);
    Product::factory()->for($this->business)->create(['name' => 'Shampoo sólido', 'code' => 'AB-120']);
    Customer::factory()->for($this->business)->create(['name' => 'Ana Pérez', 'phone' => '+584140001122']);
});

test('the palette opens, groups what it finds and keeps the focus in the field', function (): void {
    visit('/clientes')->resize(1280, 900)
        ->assertNoJavaScriptErrors()
        ->click('[data-testid="cmdk-open"]')
        ->type('[data-testid="cmdk-input"]', 'corte')
        ->waitForText('Corte de pelo')
        ->assertSee(__('search.groups.services'))
        ->assertSee(__('search.keys.move'))
        ->screenshot(filename: 'cmdk-open-desktop');
});

test('the panel is themed in the dark, not just in the light', function (): void {
    visit('/clientes')->resize(1280, 900)
        // The toggle is per page: a fresh context carries no localStorage.
        ->click('@theme-toggle')
        ->click('[data-testid="cmdk-open"]')
        ->type('[data-testid="cmdk-input"]', 'corte')
        ->waitForText('Corte de pelo')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'cmdk-open-dark');
});

test('a product code only the words lane can find comes back', function (): void {
    visit('/clientes')->resize(1280, 900)
        ->assertNoJavaScriptErrors()
        ->click('[data-testid="cmdk-open"]')
        ->type('[data-testid="cmdk-input"]', 'AB-120')
        ->waitForText('Shampoo sólido')
        ->assertSee(__('search.groups.products'))
        ->screenshot(filename: 'cmdk-code-desktop');
});

test('the arrows move the selection and escape closes it', function (): void {
    visit('/clientes')->resize(1280, 900)
        ->assertNoJavaScriptErrors()
        ->click('[data-testid="cmdk-open"]')
        ->type('[data-testid="cmdk-input"]', 'a')
        ->type('[data-testid="cmdk-input"]', 'na')
        ->waitForText('Ana Pérez')
        ->keys('[data-testid="cmdk-input"]', 'ArrowDown')
        ->assertPresent('.cmdk-row.is-cursor')
        ->screenshot(filename: 'cmdk-cursor-desktop')
        ->keys('[data-testid="cmdk-input"]', 'Escape')
        ->assertMissing('.cmdk-panel');
});

test('the match is marked and each of the first rows shows its shortcut', function (): void {
    visit('/clientes')->resize(1280, 900)
        ->assertNoJavaScriptErrors()
        ->click('[data-testid="cmdk-open"]')
        ->type('[data-testid="cmdk-input"]', 'corte')
        ->waitForText('Corte de pelo')
        ->assertPresent('.cmdk-mark')
        ->assertPresent('.cmdk-row-kbd')
        // Chromium here is Linux, so the hint must name Ctrl, not the Mac key.
        ->assertSee('Ctrl')
        ->screenshot(filename: 'cmdk-highlight-desktop');
});

test('the prefix lists things to do', function (): void {
    visit('/clientes')->resize(1280, 900)
        ->assertNoJavaScriptErrors()
        ->click('[data-testid="cmdk-open"]')
        ->type('[data-testid="cmdk-input"]', '>')
        ->waitForText(__('search.actions.service'))
        ->assertSee(__('search.groups.actions'))
        ->screenshot(filename: 'cmdk-actions-desktop');
});

test('a question is offered to the assistant instead of searched as a name', function (): void {
    visit('/clientes')->resize(1280, 900)
        ->assertNoJavaScriptErrors()
        ->click('[data-testid="cmdk-open"]')
        ->type('[data-testid="cmdk-input"]', 'cuanto vendi ayer?')
        ->wait(2)
        ->screenshot(filename: 'cmdk-ask-desktop')
        ->assertPresent('[data-testid="cmdk-ask"]');
});

test('what was opened last is offered before a letter is typed', function (): void {
    visit('/clientes')->resize(1280, 900)
        ->assertNoJavaScriptErrors()
        ->click('[data-testid="cmdk-open"]')
        ->type('[data-testid="cmdk-input"]', 'corte')
        ->waitForText('Corte de pelo')
        ->click('.cmdk-row')
        ->waitForText(__('client.services.title'))
        ->click('[data-testid="cmdk-open"]')
        ->waitForText('Corte de pelo')
        ->screenshot(filename: 'cmdk-recents-desktop');
});

test('on a phone the palette takes the whole screen and the trigger keeps its glyph', function (): void {
    visit('/clientes')->resize(390, 844)
        ->assertNoJavaScriptErrors()
        ->assertPresent('[data-testid="cmdk-open"]')
        ->click('[data-testid="cmdk-open"]')
        ->type('[data-testid="cmdk-input"]', 'corte')
        ->waitForText('Corte de pelo')
        ->screenshot(filename: 'cmdk-open-phone')
        // Full screen with no backdrop to click and no footer to read: without
        // this control the phone would have no way out.
        ->click('[data-testid="cmdk-close"]')
        ->assertMissing('.cmdk-panel');
});
