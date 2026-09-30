<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The payment history, filtered by year
|--------------------------------------------------------------------------
| Stacked on a phone, a couple of years of payments is a long scroll. The
| year comes off the BUSINESS clock: a payment made at 22:00 on 31/12 in
| Buenos Aires must land in the year its own row prints.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->business = Business::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires']);
    $this->user = User::factory()->create();
    $this->user->business()->associate($this->business)->save();
});

test('the picker offers every year the history spans, newest first', function (): void {
    Payment::factory()->for($this->business)->create(['created_at' => '2026-03-04 12:00:00']);
    Payment::factory()->for($this->business)->create(['created_at' => '2025-07-09 12:00:00']);

    Livewire::actingAs($this->user)
        ->test('payments.index')
        ->assertSet('years', ['2026' => '2026', '2025' => '2025']);
});

test('choosing a year leaves only that year in the table', function (): void {
    $thisYear = Payment::factory()->for($this->business)->create(['created_at' => '2026-03-04 12:00:00']);
    $lastYear = Payment::factory()->for($this->business)->create(['created_at' => '2025-07-09 12:00:00']);

    $screen = Livewire::actingAs($this->user)->test('payments.index');

    // Newest row first: the history is ordered by id, which in real life is
    // the order the payments arrived.
    expect($screen->get('visibleHistory')->pluck('id')->all())->toBe([$lastYear->id, $thisYear->id]);

    $screen->set('year', '2025');

    expect($screen->get('visibleHistory')->pluck('id')->all())->toBe([$lastYear->id]);
});

test('a payment made late on new year eve counts in the year its row prints', function (): void {
    // 01:30 UTC on 1 January is still 22:30 on 31 December in Buenos Aires.
    $payment = Payment::factory()->for($this->business)->create(['created_at' => '2026-01-01 01:30:00']);

    $screen = Livewire::actingAs($this->user)->test('payments.index')->set('year', '2025');

    expect($screen->get('visibleHistory')->pluck('id')->all())->toBe([$payment->id]);
});
