<?php

declare(strict_types=1);

use App\Messaging\Panel\AppointmentBooked;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Customer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| What is said about a booking once someone attends it
|--------------------------------------------------------------------------
| The reminder the customer gets, the row the owner reads and the file she
| exports all say with whom it is — and the file says how long it really lasts.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Company::factory()->create(['brand_name' => 'Aliadito']);
    Http::fake(['*' => Http::response(['key' => ['id' => 'WA-1']])]);
    app()->setLocale('es');
    $this->travelTo(CarbonImmutable::parse('2026-10-14 12:00', 'America/Argentina/Buenos_Aires'));
});

/** A connected business with the agenda on, open around the clock, and a customer who wrote to it once. */
function noticeBusiness(): array
{
    $business = Business::factory()->create([
        'name' => 'Salón Lumen',
        'timezone' => 'America/Argentina/Buenos_Aires',
        'appointments_enabled' => true,
        'whatsapp_instance' => 'atendia-demo',
        'whatsapp_connected_at' => now(),
    ]);

    foreach (range(0, 6) as $weekday) {
        $business->hours()->create(['day_of_week' => $weekday, 'opens_at' => '00:00', 'closes_at' => '23:30']);
    }

    $customer = Customer::factory()->create(['business_id' => $business->id, 'name' => 'Marta Gil']);
    $thread = Conversation::factory()->create(['business_id' => $business->id, 'contact_phone' => $customer->phone]);
    ConversationMessage::factory()->create(['business_id' => $business->id, 'conversation_id' => $thread->id]);

    return [$business, $customer];
}

function noticeBooking(Business $business, Customer $customer, array $attributes = []): Appointment
{
    $startsAt = CarbonImmutable::now($business->localTimezone())->addHours(20);

    return Appointment::factory()->create([
        'business_id' => $business->id,
        'customer_id' => $customer->id,
        'starts_at' => $startsAt->utc(),
        'ends_at' => $startsAt->addMinutes(30)->utc(),
        ...$attributes,
    ]);
}

test('the reminder tells the customer who is waiting, and says nothing of it for an unassigned booking', function (): void {
    [$business, $customer] = noticeBusiness();
    $cut = $business->services()->create(['name' => 'Corte', 'duration_minutes' => 30, 'is_bookable' => true]);
    $ana = $business->professionals()->create(['name' => 'Ana Soto']);
    noticeBooking($business, $customer, ['service_id' => $cut->id, 'professional_id' => $ana->id]);

    $this->artisan('atendia:appointment-reminders')->assertSuccessful();

    Http::assertSent(fn ($request): bool => str_contains((string) ($request->data()['text'] ?? ''), 'Corte con Ana Soto'));
});

test('an unassigned booking is reminded as always', function (): void {
    [$business, $customer] = noticeBusiness();
    $cut = $business->services()->create(['name' => 'Corte', 'duration_minutes' => 30, 'is_bookable' => true]);
    noticeBooking($business, $customer, ['service_id' => $cut->id]);

    $this->artisan('atendia:appointment-reminders')->assertSuccessful();

    Http::assertSent(fn ($request): bool => str_contains((string) ($request->data()['text'] ?? ''), 'Corte') && ! str_contains((string) ($request->data()['text'] ?? ''), 'Corte con'));
});

test('the row the owner reads says with whom the booking is', function (): void {
    [$business, $customer] = noticeBusiness();
    $cut = $business->services()->create(['name' => 'Corte', 'duration_minutes' => 30, 'is_bookable' => true]);
    $ana = $business->professionals()->create(['name' => 'Ana Soto']);
    $with = noticeBooking($business, $customer, ['service_id' => $cut->id, 'professional_id' => $ana->id]);
    $without = noticeBooking($business, $customer, ['service_id' => $cut->id]);

    expect((new AppointmentBooked($with))->payload()['service'])->toBe('Corte con Ana Soto')
        ->and((new AppointmentBooked($without))->payload()['service'])->toBe('Corte');
});

test('the exported agenda adds who attends and the length the booking really has', function (): void {
    $owner = User::factory()->create();
    $owner->business()->associate(Business::factory()->create(['name' => 'Salón Lumen']))->save();
    $business = $owner->business;
    $customer = Customer::factory()->create(['business_id' => $business->id, 'profile_name' => 'Rosa Márquez']);
    $cut = $business->services()->create(['name' => 'Corte', 'duration_minutes' => 30, 'is_bookable' => true]);
    $ana = $business->professionals()->create(['name' => 'Ana Soto']);
    $start = now()->setTime(10, 0);
    Appointment::factory()->create([
        'business_id' => $business->id, 'customer_id' => $customer->id, 'service_id' => $cut->id, 'professional_id' => $ana->id,
        'starts_at' => $start, 'ends_at' => $start->copy()->addMinutes(45),
    ]);

    $csv = (string) $this->actingAs($owner)->get(route('reports.show', ['report' => 'agenda', 'format' => 'csv', 'dia' => $start->format('Y-m-d'), 'vista' => 'day']))->assertOk()->getContent();

    // The 45 sits right after the professional on the same row: the booking's own length, not the service's 30.
    expect($csv)->toContain(__('reports/agenda.professional'))->toContain('Rosa Márquez')->toMatch('/Ana Soto[,;"\s]+45[,;"\s]/');
});
