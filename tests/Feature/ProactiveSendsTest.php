<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Customer;
use App\Models\WhatsAppLinkEvent;
use App\Services\ProactiveSends;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Messages a business starts on its own — paced, and never cold
|--------------------------------------------------------------------------
| WhatsApp bans numbers that write first to strangers, keep writing to people
| who never answer, or burst. Reminders go through one gate that checks all
| three; replies to a customer never do. The helpers live in AgendaReminderTest.
*/

beforeEach(function (): void {
    Cache::flush();
    Http::fake(['*' => Http::response(['key' => ['id' => 'WA-1']])]);

    // Wednesday noon in Buenos Aires: inside the customer send window.
    $this->travelTo(CarbonImmutable::parse('2026-10-14 12:00', 'America/Argentina/Buenos_Aires'));
});

test('a customer who never wrote and never consented is not reminded, and stays due', function (): void {
    $business = remindingBusiness();
    $stranger = Customer::factory()->create(['business_id' => $business->id]);
    $booking = bookingIn($business, 20, ['customer_id' => $stranger->id]);

    $this->artisan('atendia:appointment-reminders')->assertSuccessful();

    Http::assertNothingSent();
    expect($booking->fresh()->reminder_sent_at)->toBeNull();
});

test('consent counts as much as having written', function (): void {
    $business = remindingBusiness();
    $consenting = Customer::factory()->create(['business_id' => $business->id, 'marketing_opt_in_at' => now()->subWeek()]);
    bookingIn($business, 20, ['customer_id' => $consenting->id]);

    $this->artisan('atendia:appointment-reminders')->assertSuccessful();

    Http::assertSentCount(1);
});

test('after two unanswered messages the business stops, and an answer starts it over', function (): void {
    $business = remindingBusiness();
    $customer = customerWhoWrote($business);
    $gate = ProactiveSends::for($business);

    expect($gate->refusal($customer->phone, $customer))->toBeNull();

    $gate->sent($customer->phone);
    $gate->sent($customer->phone);

    expect($gate->refusal($customer->phone, $customer))->toBe('ignored');

    ProactiveSends::heard($business, $customer->phone);

    expect($gate->refusal($customer->phone, $customer))->toBeNull();
});

test('a young number gets a low daily cap that grows with the weeks', function (): void {
    $business = remindingBusiness();
    $customer = customerWhoWrote($business);
    $gate = ProactiveSends::for($business);

    expect($gate->dailyCap())->toBe(20);

    foreach (range(1, 20) as $unused) {
        $gate->sent('549'.random_int(100000000, 999999999));
    }

    expect($gate->refusal($customer->phone, $customer))->toBe('cap');

    // Linked a month ago: the plain cap, and yesterday's count no longer matters.
    WhatsAppLinkEvent::query()->create([
        'business_id' => $business->id, 'connected' => true, 'reason' => 'paired', 'occurred_at' => now()->subDays(40),
    ]);

    expect(ProactiveSends::for($business)->dailyCap())->toBe(150);

    $this->travelTo(now()->addDay());
    expect(ProactiveSends::for($business)->refusal($customer->phone, $customer))->toBeNull();
});

test('sends in one run wait a few seconds between them, the first one does not', function (): void {
    config()->set('atendia.outbound.gap_seconds', [3, 3]);
    Sleep::fake();

    $business = remindingBusiness();
    bookingIn($business, 20);
    bookingIn($business, 21);
    bookingIn($business, 22);

    $this->artisan('atendia:appointment-reminders')->assertSuccessful();

    Http::assertSentCount(3);
    Sleep::assertSleptTimes(2);
});

test('the same message reads differently across customers but never changes on a retry', function (): void {
    $replace = ['business' => 'Vida', 'day' => 'lunes', 'time' => '10:00', 'what' => 'consulta'];

    $first = ProactiveSends::wording('agenda.reminder', 1, $replace);

    expect(ProactiveSends::wording('agenda.reminder', 1, $replace))->toBe($first)
        ->and(ProactiveSends::wording('agenda.reminder', 2, $replace))->not->toBe($first)
        ->and($first)->toContain('Vida')->toContain('Confirmo')->toContain('Reprogramar');
});
