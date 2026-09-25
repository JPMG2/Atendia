<?php

declare(strict_types=1);

use App\Ai\Agents\AskAtendia;
use App\Models\Business;
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
