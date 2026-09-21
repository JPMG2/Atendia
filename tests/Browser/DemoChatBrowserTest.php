<?php

declare(strict_types=1);

use App\Ai\Agents\AsistenteAtendia;
use Database\Seeders\DemoBusinessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->setLocale('es');
});

test('the visitor chats with the demo clinic inside the hero phone', function (): void {
    Queue::fake();
    $this->seed(DemoBusinessSeeder::class);

    // Two entries: the grounding re-ask may consume a second prompt.
    AsistenteAtendia::fake(['Buscando…', 'La ecografía abdominal cuesta $45.000 y con obra social suele tener cobertura.']);

    $page = visit('/');

    // A chip is the visitor's first word; the reply lands as a jade bubble.
    $page->assertSee(__('landing.demo.try_label'))
        ->click('¿Cuánto sale una ecografía?')
        ->assertSee('La ecografía abdominal cuesta $45.000')
        ->screenshotElement('.hero-phone-enter', 'hero-demo-chat')
        ->assertNoJavaScriptErrors();
});
