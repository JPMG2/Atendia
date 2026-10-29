<?php

declare(strict_types=1);

use App\Classes\Main\BusinessLedger;
use App\Enums\SubscriptionStatus;
use App\Models\Business;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| "Negocios" of the admin: one piece, two readers
|--------------------------------------------------------------------------
| The web list and the mobile API read BusinessLedger, so what the phone says
| about a business is what the panel says. These tests tie the SCREEN to the
| piece; the API's own tests tie the phone to it.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create();
    $admin->syncRoles('admin');
    $this->admin = $admin->refresh();
});

function ledgerBusinessOnPlan(string $name, SubscriptionStatus $status = SubscriptionStatus::Active, array $plan = []): Business
{
    $business = Business::factory()->create(['name' => $name]);

    Subscription::factory()->for($business)->create([
        'plan' => 'negocio',
        'status' => $status,
        'billing_cycle' => 'monthly',
        'current_period_ends_at' => now()->addDays(10),
        ...$plan,
    ]);

    return $business->refresh();
}

test('the web list prints the very rows, words and count the piece gives the phone', function (): void {
    ledgerBusinessOnPlan('Odontología Norte');
    ledgerBusinessOnPlan('Panadería La Esquina', SubscriptionStatus::PastDue, ['current_period_ends_at' => now()->subDays(2)]);

    $ledger = new BusinessLedger;
    $screen = Livewire::actingAs($this->admin)->test('admin.businesses.index');

    foreach ($ledger->labels as $label) {
        $screen->assertSee($label);
    }

    foreach ($ledger->rows as $row) {
        $screen->assertSee($row['name'])->assertSee($row['plan'])->assertSee($row['state_label'])->assertSee($row['due'])->assertSee($row['owes']);
    }

    $screen->assertSeeHtml('data-testid="result-count"')->assertSee($ledger->countLabel);
});

test('the web cuts the list by state and search as the piece does, and says nothing found in its own words', function (): void {
    ledgerBusinessOnPlan('Odontología Norte');
    ledgerBusinessOnPlan('Panadería La Esquina', SubscriptionStatus::Paused);

    $screen = Livewire::actingAs($this->admin)->test('admin.businesses.index')
        ->set('state', 'paused')
        ->assertSee('Panadería La Esquina')
        ->assertDontSee('Odontología Norte');

    $screen->set('state', '')->set('search', 'zzzz')->assertSee((new BusinessLedger('zzzz'))->emptyText);
});

test('the landing demos are never listed, counted or opened as a client, on the web or the phone', function (): void {
    ledgerBusinessOnPlan('Odontología Norte');
    $demo = ledgerBusinessOnPlan('Kiosco de Muestra', SubscriptionStatus::Paused);
    $demo->forceFill(['is_demo' => true])->save();

    $ledger = new BusinessLedger;

    expect(collect($ledger->rows)->pluck('name')->all())->toBe(['Odontología Norte'])
        ->and(Business::directoryTotal())->toBe(1)
        ->and(Business::directoryMatching('', 'paused'))->toBe(0)
        ->and($ledger->ficha($demo->id))->toBeNull();

    Livewire::actingAs($this->admin)->test('admin.businesses.index')
        ->assertSee('Odontología Norte')
        ->assertDontSee('Kiosco de Muestra')
        ->set('state', 'paused')
        ->assertDontSee('Kiosco de Muestra');
});

test('the web ficha prints the cards and payments of the piece, and what the plan row says about leaving', function (): void {
    $business = ledgerBusinessOnPlan('Estudio Contable Ríos', SubscriptionStatus::Active, ['canceled_at' => now()->subDay()]);
    Payment::factory()->for($business)->create(['plan' => 'negocio', 'status' => 'pending']);

    $ficha = (new BusinessLedger)->fichaOf(Business::ledger($business->id));
    $screen = Livewire::actingAs($this->admin)->test('admin.businesses.index')->call('toggle', $business->id);

    foreach ($ficha['cards'] as $card) {
        $screen->assertSee($card['label'])->assertSee($card['value'])->assertSee($card['sub']);
    }

    foreach ($ficha['payments']['rows'] as $payment) {
        $screen->assertSee($payment['concept'])->assertSee($payment['amount'])->assertSee($payment['status']);
    }

    // The sentences the web prints when the plan is on its way out are the note the phone gets.
    expect($ficha['note'])->not->toBeNull();
    $screen->assertSee(trim(__('admin.businesses.cancel.asked_on', ['date' => $business->subscription->canceled_at->format('d/m/Y')])));
});
