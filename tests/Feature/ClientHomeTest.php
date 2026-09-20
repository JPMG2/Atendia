<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Client home — two states derived from real data (mock switch died 2026-09-20)
|--------------------------------------------------------------------------
| A fresh client meets the setup guide (the checklist IS the dashboard —
| KPIs at zero would only depress); the first real thread earns the
| day-to-day view with the month's real numbers. These tests pin that.
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
        ->assertDontSee(__('statistics.kpis.conversations'));
});

test('the home shows the real plan usage strip linking to the plan screen', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    $user->business()->associate(Business::factory()->create())->save();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSee(__('plan.meters.conversations'))
        ->assertSee(__('plan.meters.conversations_of', ['used' => 0, 'cap' => '1.000']))
        ->assertSee(route('my-plan'));
});

test('the usage strip stays away from a user without a business yet', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))->assertDontSee(__('plan.meters.conversations'));
});

test('the business wears its "sin conectar" pill pointing at the contact card', function (): void {
    // The "conectar después" promise kept in sight while nothing answers.
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    $user->business()->associate(Business::factory()->create(['name' => 'Clínica Vida']))->save();
    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertSee('Clínica Vida · '.__('client.home.disconnected'))
        ->assertSee(route('my-business.contacto'), false);
});

test('a connected business wears no pill', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    $user->business()->associate(Business::factory()->create([
        'whatsapp_connected_at' => now(),
    ]))->save();
    $this->actingAs($user);

    $this->get(route('dashboard'))->assertDontSee(__('client.home.disconnected'));
});

test('with no business there is no connection pill', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))->assertDontSee(__('client.home.disconnected'));
});

test('the account step arrives already ticked, endowed-progress style', function (): void {
    $this->actingAs(User::factory()->create());

    Livewire::test('client.home')
        ->assertSee(__('client.setup.steps.account.label'))
        ->assertSee(__('client.setup.progress', ['done' => 1, 'total' => 5]));
});

test('the WhatsApp step is the hero with its own primary call to action', function (): void {
    $this->actingAs(User::factory()->create());

    Livewire::test('client.home')
        ->assertSeeHtml('is-hero')
        ->assertSee(__('client.setup.steps.whatsapp.cta'))
        ->assertSeeHtml(route('whatsapp'));
});

test('the setup steps tick themselves from real data', function (): void {
    $user = User::factory()->create();
    $user->business()->associate(Business::factory()->create())->save();
    Service::factory()->count(2)->create(['business_id' => $user->business_id]);
    $this->actingAs($user);

    // Account + catalog done, the rest honestly pending.
    Livewire::test('client.home')
        ->assertSee(__('client.setup.progress', ['done' => 2, 'total' => 5]));
});

test('the first real thread earns the day-to-day view with real numbers', function (): void {
    $user = User::factory()->create();
    $user->business()->associate(Business::factory()->create())->save();
    $thread = Conversation::factory()->create([
        'business_id' => $user->business_id,
        'contact_name' => 'Carla',
    ]);
    ConversationMessage::factory()->for($thread)->create([
        'business_id' => $user->business_id,
        'body' => '¿Tienen turnos?',
    ]);
    $this->actingAs($user);

    Livewire::test('client.home')
        ->assertSee(__('statistics.kpis.conversations'))
        ->assertSee(__('client.recent.title'))
        ->assertSee('Carla')
        ->assertSee('¿Tienen turnos?')
        ->assertDontSee(__('client.setup.sub'));
});

test('empty widgets preview what will fill them instead of a naked zero', function (): void {
    $this->actingAs(User::factory()->create());

    Livewire::test('client.home')
        ->assertSee(__('client.previews.conversations_text'))
        ->assertSee(__('client.previews.metrics_text'));
});
