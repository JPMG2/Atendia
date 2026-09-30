<?php

declare(strict_types=1);

use App\Ai\Agents\AskAtendia;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->user = User::factory()->create(['name' => 'Carla Ruiz']);
    $this->user->assignRole('client');
    $this->user->business()->associate(Business::factory()->create())->save();
    $this->actingAs($this->user);
});

test('the topbar icon opens the assistant, which introduces itself and answers', function (): void {
    AskAtendia::fake(['Hoy no hubo consultas.']);

    $page = visit('/dashboard')->assertNoJavaScriptErrors();
    $page->assertMissing('.slide-over');

    $page->click('@ask-atendia')
        ->assertSee(__('ask.hello', ['name' => 'Carla']))
        ->assertSee(__('ask.today.title'))
        ->click(__('ask.suggestions.unresolved'))
        ->assertSee('Hoy no hubo consultas.')
        ->assertNoJavaScriptErrors();
});

test('a statistics block opens the assistant with its question already asked', function (): void {
    AskAtendia::fake(['El gráfico muestra tus conversaciones por día.']);

    visit('/estadisticas')
        ->click(__('ask.chart.button'))
        ->assertSee(__('ask.chart.topics'))
        ->assertSee('El gráfico muestra tus conversaciones por día.')
        ->assertNoJavaScriptErrors();
});

test('the day card shows the bookings still to come today, with the agenda behind them', function (): void {
    AskAtendia::fake(['Tenés 1 turno hoy.']);
    $business = $this->user->business;
    $business->update(['appointments_enabled' => true, 'appointment_slot_minutes' => 30]);
    $today = now($business->localTimezone());

    // Late in the day on purpose: a booking already started would not be waiting.
    Appointment::factory()->create([
        'business_id' => $business->id,
        'customer_id' => Customer::factory()->create(['business_id' => $business->id, 'name' => 'Ana Gómez'])->id,
        'starts_at' => $today->copy()->endOfDay()->subMinutes(30)->utc(),
        'ends_at' => $today->copy()->endOfDay()->utc(),
    ]);

    visit('/dashboard')
        ->click('@ask-atendia')
        ->assertSee(trans_choice('ask.today.appointments', 1, ['count' => 1]))
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'ask-today-appointments');
});
