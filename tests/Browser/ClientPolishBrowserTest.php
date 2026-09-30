<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\TeamInvitation;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->business = Business::factory()->create(['name' => 'Clínica Vida']);
    $user = User::factory()->create(['name' => 'María González']);
    $user->business()->associate($this->business)->save();
    $this->actingAs($user);
});

test('a disconnected business sees the yellow chip, the short search and the connect action', function (): void {
    visit('/clientes')->resize(1280, 900)
        ->assertNoJavaScriptErrors()
        ->assertSee(__('whatsapp.topbar_disconnected'))
        ->assertSee(__('whatsapp.connect.cta'))
        ->assertAttribute('input[name="search"]', 'placeholder', __('menu.search_placeholder'))
        ->screenshot(filename: 'polish-customers-desktop');

    visit('/conversaciones')->resize(560, 900)
        ->assertNoJavaScriptErrors()
        ->assertSee(__('whatsapp.connect.cta'))
        ->click('@theme-toggle')
        ->screenshot(filename: 'polish-conversations-phone-dark');

    visit('/clientes')->resize(1280, 900)
        ->click(__('whatsapp.topbar_disconnected'))
        ->assertPathIs('/whatsapp');
});

test('the pending invitation cancels with a red cross', function (): void {
    TeamInvitation::factory()->create(['business_id' => $this->business->id, 'email' => 'martin@shop.test']);

    visit('/equipo')->resize(1280, 900)
        ->assertNoJavaScriptErrors()
        ->assertSee('martin@shop.test')
        ->assertPresent('button.icon-btn-danger[aria-label="'.__('team.people.cancel').'"]')
        ->screenshot(filename: 'polish-team-invitation');
});

test('my business answers from its own folder', function (): void {
    visit('/negocio')->resize(1280, 900)
        ->assertNoJavaScriptErrors()
        ->assertSee(__('client.business.title'))
        ->screenshot(filename: 'polish-my-business');
});

test('a recent row on the home opens the thread it names', function (): void {
    $thread = Conversation::factory()->create([
        'business_id' => $this->business->id,
        'contact_name' => 'Carla Ruiz',
        'last_message_at' => now(),
    ]);
    ConversationMessage::factory()->for($thread)->create([
        'business_id' => $this->business->id,
        'body' => '¿Tienen turnos para el jueves?',
    ]);

    visit('/dashboard')->resize(1280, 900)
        ->assertNoJavaScriptErrors()
        ->assertSee(__('client.recent.title'))
        ->screenshot(filename: 'polish-home-recent')
        ->click('Carla Ruiz')
        ->assertPathIs('/conversaciones')
        ->assertSee($thread->contact_phone)
        ->screenshot(filename: 'polish-home-recent-lands');

    // The cold path: the link a handoff ping mails to a phone, opened fresh.
    visit(route('conversations', ['hilo' => $thread->id]))->resize(1280, 900)
        ->assertNoJavaScriptErrors()
        ->assertSee($thread->contact_phone);
});
