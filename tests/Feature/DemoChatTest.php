<?php

declare(strict_types=1);

use App\Ai\Agents\AsistenteAtendia;
use App\Models\Business;
use App\Models\DemoMetric;
use App\Models\KnowledgeDocument;
use Database\Seeders\DemoBusinessSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
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
        ->assertJson(['reply' => 'Sí, tenemos turnos el jueves a la mañana.', 'done' => false, 'left' => 3]);
});

test('the rubro selector answers with that rubro\'s demo business', function (): void {
    Queue::fake();
    $this->seed(DemoBusinessSeeder::class);

    AsistenteAtendia::fake(['Buscando…', 'El corte de dama cuesta $18.000.']);

    $this->postJson(route('demo.message'), ['message' => '¿Cuánto sale el corte?', 'rubro' => 'peluqueria'])
        ->assertOk()
        ->assertJsonPath('reply', 'El corte de dama cuesta $18.000.');

    expect(Business::demo('peluqueria')->name)->toBe('Peluquería Lumen')
        ->and(Business::demo('kiosco')->name)->toBe('Kiosco El Faro');
});

test('a carousel rubro answers with its own demo business', function (): void {
    Queue::fake();
    $this->seed(DemoBusinessSeeder::class);

    AsistenteAtendia::fake(['Buscando…', 'Las tortas por encargo salen desde $28.000 el kilo.']);

    $this->postJson(route('demo.message'), ['message' => '¿Hacen tortas por encargo?', 'rubro' => 'panaderia'])
        ->assertOk()
        ->assertJsonPath('reply', 'Las tortas por encargo salen desde $28.000 el kilo.');

    expect(Business::demo('dr-juan')->name)->toBe('Dr. Juan Herrera')
        ->and(Business::demo('veterinaria')->name)->toBe('Veterinaria Patitas');
});

test('an unknown rubro is refused before it reaches the model', function (): void {
    $this->postJson(route('demo.message'), ['message' => '¿Hola?', 'rubro' => 'banco'])
        ->assertUnprocessable();
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

    expect(Business::query()->whereIn('billing_email', Business::DEMO_EMAILS)->count())->toBe(8)
        ->and(Business::demo()->name)->toBe('Clínica Vida')
        ->and(KnowledgeDocument::query()->where('business_id', Business::demo()->id)->count())->toBe(6)
        ->and(KnowledgeDocument::query()->where('business_id', Business::demo('peluqueria')->id)->count())->toBe(4)
        ->and(KnowledgeDocument::query()->where('business_id', Business::demo('panaderia')->id)->count())->toBe(4)
        ->and(KnowledgeDocument::query()->where('business_id', Business::demo('kiosco')->id)->count())->toBe(4);
});

test('the demo businesses never inflate the landing social proof', function (): void {
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

test('the hero phone offers the rubro selector and the english chip', function (): void {
    $this->get('/')
        ->assertSee(__('landing.demo.rubro_label'))
        ->assertSee('Peluquería')
        ->assertSee('Kiosco')
        ->assertSee('¿Cuánto sale el corte?')
        ->assertSee('How much is an ultrasound?')
        ->assertSee(trans_choice('landing.demo.left', 2, ['count' => '__N__']));
});

test('the rubro carousel ships all 8 pills but only the first 3 visible', function (): void {
    $response = $this->get('/')
        ->assertSee('data-demo-rubro-track', false)
        ->assertSee('Dr. Juan')
        ->assertSee('Panadería')
        ->assertSee('Restaurante')
        ->assertSee('Ferretería')
        ->assertSee('Veterinaria')
        ->assertSee('Do you do grooming?');

    // Every pill beyond the original window is born hidden: the row keeps
    // its 3-pill width and hero.js rotates the rest through.
    expect(substr_count($response->getContent(), 'data-demo-rubro='))->toBe(count(Business::DEMO_EMAILS))
        ->and(preg_match_all('/data-demo-rubro=[^>]+display: none/', $response->getContent()))->toBe(5);
});

test('every rubro ships its own scripted conversation, some in english', function (): void {
    $this->get('/')
        ->assertSee('data-live-pools', false)
        // A themed line per pool: the phone talks like THAT business.
        ->assertSee('¿Están abiertos ahora?')
        ->assertSee('Mi gato no quiere comer')
        ->assertSee('¿Mesa para 4 hoy a las 21?')
        // English exchanges beyond the clinic: the plus, demonstrated.
        ->assertSee('Do you make custom cakes?')
        ->assertSee('Do you do balayage?');
});

test('each pill carries its live dot, pressed state and rubro-named invite', function (): void {
    $response = $this->get('/')
        ->assertSee('pill-live-dot')
        ->assertSee('Crear el asistente de mi panadería')
        ->assertSee('Crear el asistente de mi veterinaria');

    // Exactly one pill is born pressed: the clinic, today's opening rubro.
    expect(substr_count($response->getContent(), 'aria-pressed="true"'))->toBe(1)
        ->and(substr_count($response->getContent(), 'aria-pressed="false"'))->toBe(7)
        ->and(substr_count($response->getContent(), 'data-demo-cta-label='))->toBe(count(Business::DEMO_EMAILS));
});

test('the funnel tallies sessions and messages per day', function (): void {
    Queue::fake();
    $this->seed(DemoBusinessSeeder::class);

    AsistenteAtendia::fake(['Uno.', 'Uno.', 'Dos.', 'Dos.']);

    $this->postJson(route('demo.message'), ['message' => '¿Tienen turnos?']);
    $this->postJson(route('demo.message'), ['message' => '¿Y precios?']);

    $metric = DemoMetric::query()->sole();

    expect($metric->sessions)->toBe(1)
        ->and($metric->messages)->toBe(2)
        ->and($metric->registrations)->toBe(0);
});

test('a registration after trying the demo closes the funnel', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->withSession(['demo_messages' => 2])->post(route('register'), [
        'name' => 'Carla Demo',
        'email' => 'carla@example.com',
        'password' => 'Segura#2026',
        'password_confirmation' => 'Segura#2026',
    ]);

    expect(DemoMetric::query()->sole()->registrations)->toBe(1);
});

test('a registration with no demo behind it counts nothing', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->post(route('register'), [
        'name' => 'Bruno Directo',
        'email' => 'bruno@example.com',
        'password' => 'Segura#2026',
        'password_confirmation' => 'Segura#2026',
    ]);

    expect(DemoMetric::query()->count())->toBe(0);
});

test('the phone ships the per-rubro greeting and the share door', function (): void {
    $this->get('/')
        ->assertSee('Soy el asistente de Peluquería Lumen')
        ->assertSee(__('landing.demo.share'))
        ->assertSee('__NAME__', false);
});
