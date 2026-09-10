<?php

declare(strict_types=1);

use App\Classes\Main\AssistantPreview;
use App\Models\Business;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Live WhatsApp simulator — the offer screens' right column
|--------------------------------------------------------------------------
| One builder feeds the wizard rail and the dashboard phone. These tests pin
| that the conversation speaks the tenant's OWN rows (never a canned demo),
| escapes what the user typed, and that both offer screens carry it.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('the builder answers with the loaded offer and escapes what the user typed', function (): void {
    $messages = AssistantPreview::messages('Ana & Co', ['Corte de dama'], ['Hilo de seda']);

    expect($messages)->toHaveCount(6)
        ->and($messages[1]['html'])->toContain('Ana &amp; Co')
        // The question repeats the row EXACTLY as loaded — never re-cased.
        ->and($messages[2]['html'])->toContain('Corte de dama')
        ->and($messages[3]['html'])->toContain('<b>Corte de dama</b>')
        ->and($messages[4]['html'])->toContain('Hilo de seda');

    // No name yet means no conversation: the phone shows its empty hint.
    expect(AssistantPreview::messages(''))->toBe([]);
});

test('the simulator speaks for the signed-in business with its real rows', function (): void {
    $business = Business::factory()->create(['name' => 'Costuras Mary']);
    $business->services()->create(['name' => 'Dobladillo']);
    $business->products()->create(['name' => 'Hilo de seda']);

    $this->actingAs(User::factory()->create(['business_id' => $business->id])->refresh());

    $component = Livewire::test('client.ws-simulator')
        ->assertSet('businessName', 'Costuras Mary')
        ->assertSee(__('client.simulator.tag'))
        ->assertSee(__('client.simulator.sub'))
        ->assertSee('Costuras Mary');

    expect($component->get('messages'))->toHaveCount(6);
});

test('with nothing loaded the simulator teaches instead of faking a demo', function (): void {
    $this->actingAs(User::factory()->create()->refresh());

    Livewire::test('client.ws-simulator')
        ->assertSet('messages', [])
        ->assertSee(__('client.simulator.empty'));
});

test('both offer screens carry the live simulator beside the list', function (): void {
    $this->actingAs(User::factory()->create()->refresh());

    $this->get(route('my-services'))->assertSuccessful()->assertSee(__('client.simulator.tag'));
    $this->get(route('my-products'))->assertSuccessful()->assertSee(__('client.simulator.tag'));
});
