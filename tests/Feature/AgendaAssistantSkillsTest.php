<?php

declare(strict_types=1);

use App\Ai\Agents\AsistenteAtendia;
use App\Ai\Tools\BookCustomerAppointment;
use App\Ai\Tools\CheckFreeSlots;
use App\Ai\Tools\ManageCustomerAppointments;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\Customer;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

/** A business open 09:00-12:00 on weekdays with the agenda on, and its customer. */
function agendaContext(array $attributes = []): array
{
    $business = Business::factory()->create([
        'timezone' => 'America/Argentina/Buenos_Aires',
        'appointments_enabled' => true,
        'appointment_slot_minutes' => 30,
        ...$attributes,
    ]);

    foreach (range(1, 5) as $weekday) {
        $business->hours()->create(['day_of_week' => $weekday, 'opens_at' => '09:00', 'closes_at' => '12:00']);
    }

    return [$business, Customer::factory()->create(['business_id' => $business->id, 'name' => 'Ana'])];
}

function comingMonday(Business $business): CarbonImmutable
{
    return CarbonImmutable::now($business->localTimezone())->addWeek()->startOfWeek();
}

test('the agenda skills reach only a business that gives slots', function (): void {
    [$business, $customer] = agendaContext();
    [$bakery, $bakeryCustomer] = agendaContext(['appointments_enabled' => false]);

    expect(CheckFreeSlots::forAssistant(new AsistenteAtendia($business, null, $customer)))->not->toBeNull()
        ->and(CheckFreeSlots::forAssistant(new AsistenteAtendia($bakery, null, $bakeryCustomer)))->toBeNull()
        ->and(BookCustomerAppointment::forAssistant(new AsistenteAtendia($bakery, null, $bakeryCustomer)))->toBeNull()
        ->and(ManageCustomerAppointments::forAssistant(new AsistenteAtendia($bakery, null, $bakeryCustomer)))->toBeNull();
});

test('a business without the agenda reads nothing about turnos in its instructions', function (): void {
    [$business] = agendaContext();
    [$bakery] = agendaContext(['appointments_enabled' => false]);

    expect((string) (new AsistenteAtendia($business))->instructions())->toContain('AGENDA DE TURNOS')
        ->and((string) (new AsistenteAtendia($bakery))->instructions())->not->toContain('AGENDA DE TURNOS');
});

test('the free slots of one day come back as real hours', function (): void {
    [$business] = agendaContext();
    $monday = comingMonday($business);

    $answer = (string) (new CheckFreeSlots($business))->handle(new Request(['date' => $monday->format('Y-m-d')]));

    expect($answer)->toContain('09:00')->toContain('11:30')->toContain('30 min');
});

test('an impossible date is refused instead of being rolled forward', function (): void {
    [$business] = agendaContext();

    expect((string) (new CheckFreeSlots($business))->handle(new Request(['date' => '2026-02-31'])))
        ->toContain('Fecha inválida');
});

test('a full day answers with the next free hours', function (): void {
    [$business, $customer] = agendaContext(['appointments_per_day' => 1]);
    $monday = comingMonday($business);

    Appointment::factory()->create([
        'business_id' => $business->id,
        'customer_id' => $customer->id,
        'starts_at' => $monday->setTime(9, 0)->utc(),
        'ends_at' => $monday->setTime(9, 30)->utc(),
    ]);

    $answer = (string) (new CheckFreeSlots($business))->handle(new Request(['date' => $monday->format('Y-m-d')]));

    expect($answer)->toContain('No hay turnos libres')->toContain('Los siguientes libres');
});

test('the assistant books the chosen hour and refuses one already taken', function (): void {
    [$business, $customer] = agendaContext();
    $monday = comingMonday($business)->setTime(9, 0);
    $tool = new BookCustomerAppointment($business, $customer);

    expect((string) $tool->handle(new Request(['starts_at' => $monday->format('Y-m-d H:i')])))
        ->toContain('Turno reservado')
        ->and((string) $tool->handle(new Request(['starts_at' => $monday->format('Y-m-d H:i')])))
        ->toContain('ya no está libre')->toContain('09:30');
});

test('booking a service takes its own duration', function (): void {
    [$business, $customer] = agendaContext();
    $business->services()->create(['name' => 'Ecodoppler', 'duration_minutes' => 60, 'is_bookable' => true]);
    $monday = comingMonday($business)->setTime(9, 0);

    (new BookCustomerAppointment($business, $customer))->handle(new Request([
        'starts_at' => $monday->format('Y-m-d H:i'),
        'service' => 'ecodoppler',
    ]));

    expect($business->appointments()->sole()->durationMinutes())->toBe(60);
});

test('a bad hour is refused before anything is booked', function (): void {
    [$business, $customer] = agendaContext();

    expect((string) (new BookCustomerAppointment($business, $customer))->handle(new Request(['starts_at' => 'el lunes'])))
        ->toContain('Horario inválido')
        ->and($business->appointments()->count())->toBe(0);
});

test('the customer lists, moves and cancels their own booking', function (): void {
    [$business, $customer] = agendaContext();
    $monday = comingMonday($business);
    (new BookCustomerAppointment($business, $customer))->handle(new Request(['starts_at' => $monday->setTime(9, 0)->format('Y-m-d H:i')]));
    $tool = new ManageCustomerAppointments($business, $customer);

    expect((string) $tool->handle(new Request(['action' => 'list'])))->toContain('09:00')
        ->and((string) $tool->handle(new Request([
            'action' => 'move',
            'new_starts_at' => $monday->setTime(11, 0)->format('Y-m-d H:i'),
        ])))->toContain('Turno movido')
        ->and((string) $tool->handle(new Request(['action' => 'cancel'])))->toContain('Turno cancelado')
        ->and((string) $tool->handle(new Request(['action' => 'list'])))->toContain('no tiene turnos');
});

test('a booking of another customer is never reachable', function (): void {
    [$business, $customer] = agendaContext();
    $other = Customer::factory()->create(['business_id' => $business->id]);
    $monday = comingMonday($business);
    (new BookCustomerAppointment($business, $other))->handle(new Request(['starts_at' => $monday->setTime(9, 0)->format('Y-m-d H:i')]));
    $theirs = $business->appointments()->sole();

    (new BookCustomerAppointment($business, $customer))->handle(new Request(['starts_at' => $monday->setTime(10, 0)->format('Y-m-d H:i')]));

    expect((string) (new ManageCustomerAppointments($business, $customer))->handle(new Request([
        'action' => 'cancel',
        'appointment_id' => $theirs->id,
    ])))->toContain('no es de este cliente')
        ->and($theirs->fresh()->status->holdsSlot())->toBeTrue();
});
