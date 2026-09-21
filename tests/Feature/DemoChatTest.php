<?php

declare(strict_types=1);

use App\Ai\Agents\AsistenteAtendia;
use App\Models\Business;
use App\Models\KnowledgeDocument;
use Database\Seeders\DemoBusinessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('the demo answers as the seeded clinic with the real assistant', function (): void {
    Queue::fake();
    $this->seed(DemoBusinessSeeder::class);

    // Two entries: the grounding re-ask may consume a second prompt.
    AsistenteAtendia::fake(['Buscando…', 'Sí, tenemos turnos el jueves a la mañana.']);

    $this->postJson(route('demo.message'), ['message' => '¿Tienen turno esta semana?'])
        ->assertOk()
        ->assertJson(['reply' => 'Sí, tenemos turnos el jueves a la mañana.', 'done' => false]);
});

test('the session budget runs out into the register invite', function (): void {
    Queue::fake();
    $this->seed(DemoBusinessSeeder::class);
    config()->set('atendia.demo.session_cap', 2);

    AsistenteAtendia::fake(['Uno.', 'Uno.', 'Dos.', 'Dos.']);

    $this->postJson(route('demo.message'), ['message' => '¿Precio de la ecografía?'])
        ->assertJson(['done' => false]);

    // The last budgeted reply already carries the goodbye flag.
    $this->postJson(route('demo.message'), ['message' => '¿Y el electro?'])
        ->assertJson(['done' => true])
        ->assertJsonPath('reply', 'Dos.');

    // Beyond the budget nothing reaches the model: invite only.
    $this->postJson(route('demo.message'), ['message' => '¿Hola?'])
        ->assertExactJson(['done' => true]);
});

test('the daily fuse cuts the whole landing off quietly', function (): void {
    Queue::fake();
    $this->seed(DemoBusinessSeeder::class);
    config()->set('atendia.demo.daily_cap', 0);

    $this->postJson(route('demo.message'), ['message' => '¿Tienen turnos?'])
        ->assertExactJson(['done' => true]);
});

test('without a seeded demo business the invite answers, never an error', function (): void {
    $this->postJson(route('demo.message'), ['message' => '¿Hola?'])
        ->assertOk()
        ->assertExactJson(['done' => true]);
});

test('an empty or oversized message never reaches the model', function (): void {
    $this->postJson(route('demo.message'), ['message' => ''])->assertUnprocessable();
    $this->postJson(route('demo.message'), ['message' => str_repeat('a', 201)])->assertUnprocessable();
});

test('the endpoint is throttled per visitor', function (): void {
    foreach (range(1, 6) as $i) {
        $this->postJson(route('demo.message'), ['message' => '¿Hola?'])->assertOk();
    }

    $this->postJson(route('demo.message'), ['message' => '¿Hola?'])->assertStatus(429);
});

test('the demo seeder is idempotent and feeds the clinic knowledge', function (): void {
    Queue::fake();

    $this->seed(DemoBusinessSeeder::class);
    $this->seed(DemoBusinessSeeder::class);

    $demo = Business::demo();

    expect(Business::query()->where('billing_email', Business::DEMO_EMAIL)->count())->toBe(1)
        ->and($demo->name)->toBe('Clínica Vida')
        ->and(KnowledgeDocument::query()->where('business_id', $demo->id)->count())->toBe(6);
});

test('the demo business never inflates the landing social proof', function (): void {
    Queue::fake();
    $this->seed(DemoBusinessSeeder::class);
    Business::factory()->create();

    expect(Business::servedCount())->toBe(1);
});

test('the hero phone offers the interactive composer', function (): void {
    $this->get('/')
        ->assertSee(__('landing.demo.try_label'))
        ->assertSee('¿Cuánto sale una ecografía?')
        ->assertSee(route('demo.message'), false)
        ->assertSee('csrf-token', false);
});
