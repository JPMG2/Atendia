<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create(['name' => 'María González']);
    $user->business()->associate(Business::factory()->create(['name' => 'Clínica Vida']))->save();
    $this->actingAs($user);
});

test('customers and conversations share one empty state, on desktop and phone', function (): void {
    visit('/clientes')->resize(1280, 900)
        ->assertNoJavaScriptErrors()
        ->assertSee(__('client.customers.empty_title'))
        ->assertSee(__('client.customers.empty_body'))
        ->assertPresent('[data-testid="cmdk-open"]')
        ->screenshot(filename: 'empty-state-customers-desktop');

    visit('/conversaciones')->resize(560, 900)
        ->assertNoJavaScriptErrors()
        ->assertSee(__('client.conversations.empty_title'))
        ->click('@theme-toggle')
        ->screenshot(filename: 'empty-state-conversations-phone-dark');
});
