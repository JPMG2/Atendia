<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
});

/*
|--------------------------------------------------------------------------
| Conversations — the date filter, in a real browser
|--------------------------------------------------------------------------
| Flatpickr and the Alpine glue live in JS the PHP tests never execute:
| only a browser proves the calendar actually opens and commits.
*/

test('the date filter opens the house-dressed calendar and picking a day filters the list', function (): void {
    $user = User::factory()->create();
    $user->business()->associate(Business::factory()->create())->save();

    $conversation = Conversation::factory()->create([
        'business_id' => $user->business_id,
        'contact_name' => 'Carla',
        'contact_phone' => '5491111111111',
    ]);
    ConversationMessage::factory()->for($conversation)->create([
        'business_id' => $user->business_id, 'body' => 'Hola',
    ]);

    $this->actingAs($user);

    $page = visit('/conversaciones');

    $page->assertSee('Carla')
        ->click('#if-dates')
        ->assertVisible('.flatpickr-calendar.open')
        ->screenshotElement('.flatpickr-calendar.open', 'calendar-open')
        ->assertNoJavaScriptErrors();

    // One click = one day; clicking outside closes and commits. Today holds
    // the exchange, so the thread must survive the filter round-trip.
    $page->click('.flatpickr-calendar.open .flatpickr-day.today')
        ->assertNoJavaScriptErrors()
        ->assertValue('#if-dates', now()->format('d/m/Y'))
        ->click('.page-head-title')
        ->assertSee('Carla')
        // Livewire re-rendered on commit: wire:ignore must have kept the
        // date flatpickr painted, or the field looks empty while filtering.
        ->assertValue('#if-dates', now()->format('d/m/Y'))
        ->assertNoJavaScriptErrors();
});

test('the customer sheet slides over the inbox with the fiche fields', function (): void {
    $user = User::factory()->create();
    $user->business()->associate(Business::factory()->create())->save();

    $customer = Customer::factory()->create([
        'business_id' => $user->business_id,
        'phone' => '5491111111111',
        'profile_name' => 'Carli',
    ]);
    $thread = Conversation::factory()->create([
        'business_id' => $user->business_id,
        'contact_name' => 'Carla',
        'contact_phone' => '5491111111111',
        'customer_id' => $customer->id,
    ]);
    ConversationMessage::factory()->for($thread)->create([
        'business_id' => $user->business_id, 'body' => 'Hola',
    ]);

    $this->actingAs($user);

    $page = visit('/conversaciones');

    $page->click('Carla')
        ->click(__('client.customers.open'))
        ->assertSee(__('client.customers.field_birthday'))
        ->assertSee(__('client.customers.opt_in_button'))
        ->assertNoJavaScriptErrors();
});

test('walking back in time flows through the thread island', function (): void {
    $user = User::factory()->create();
    $user->business()->associate(Business::factory()->create())->save();

    $thread = Conversation::factory()->create([
        'business_id' => $user->business_id,
        'contact_name' => 'Carla',
        'contact_phone' => '5491111111111',
    ]);

    foreach (range(1, 35) as $i) {
        ConversationMessage::factory()->for($thread)->create([
            'business_id' => $user->business_id,
            'body' => "Mensaje {$i}",
        ]);
    }

    $this->actingAs($user);

    // The island round-trip only exists in a real browser: the PHP tests
    // exercise loadOlder through a full render, never the scoped request.
    $page = visit('/conversaciones');

    $page->click('Carla')
        ->assertSee('Mensaje 35')
        ->click(__('client.conversations.older'))
        ->assertSee('Mensaje 1')
        ->assertNoJavaScriptErrors();
});
