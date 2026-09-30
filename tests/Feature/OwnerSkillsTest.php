<?php

declare(strict_types=1);

use App\Ai\Agents\AskAtendia;
use App\Ai\Tools\OwnerAppointments;
use App\Ai\Tools\OwnerBirthdays;
use App\Ai\Tools\OwnerConversations;
use App\Ai\Tools\OwnerFreeSlots;
use App\Ai\Tools\OwnerPlanUsage;
use App\Ai\Tools\OwnerStatistics;
use App\Ai\Tools\PanelGuide;
use App\Enums\AppointmentStatus;
use App\Enums\ConversationStatus;
use App\Enums\MessageDirection;
use App\Enums\QuestionResolution;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\ConversationAnalysis;
use App\Models\ConversationMessage;
use App\Models\ConversationQuestion;
use App\Models\Customer;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

function ownerToday(Business $business): string
{
    return now($business->localTimezone())->toDateString();
}

function inboundThread(Business $business, string $name, ConversationStatus $status = ConversationStatus::Open): Conversation
{
    $thread = Conversation::factory()->create(['business_id' => $business->id, 'contact_name' => $name, 'status' => $status]);
    ConversationMessage::factory()->for($thread)->create(['business_id' => $business->id, 'direction' => MessageDirection::In]);

    return $thread;
}

test('conversations counts only this business and links each thread', function (): void {
    $business = Business::factory()->create();
    $waiting = inboundThread($business, 'María López', ConversationStatus::Team);
    inboundThread($business, 'Juan Pérez');
    inboundThread(Business::factory()->create(), 'De otro negocio');

    $answer = (string) (new OwnerConversations($business))->handle(new Request([
        'from' => ownerToday($business), 'to' => ownerToday($business), 'only_waiting' => false,
    ]));

    expect($answer)->toContain('2 conversaciones con mensajes de clientes')
        ->toContain('Esperando a una persona del equipo en este momento: 1.')
        ->toContain('María López')
        ->toContain(route('conversations', ['hilo' => $waiting->id]))
        ->not->toContain('De otro negocio');
});

test('conversations says what customers actually asked and who answered', function (): void {
    $business = Business::factory()->create();
    $thread = inboundThread($business, 'María López');
    $message = $thread->messages()->first();
    $analysis = ConversationAnalysis::query()->create(['business_id' => $business->id, 'conversation_id' => $thread->id, 'first_message_id' => $message->id, 'last_message_id' => $message->id, 'sentiment' => 'neutral']);
    ConversationQuestion::query()->create([
        'business_id' => $business->id, 'conversation_id' => $thread->id, 'conversation_analysis_id' => $analysis->id,
        'conversation_message_id' => $message->id, 'question' => '¿Tienen turnos el sábado?',
        'resolved_by' => QuestionResolution::Assistant, 'asked_at' => now(),
    ]);

    $answer = (string) (new OwnerConversations($business))->handle(new Request([
        'from' => ownerToday($business), 'to' => ownerToday($business), 'only_waiting' => false,
    ]));

    expect($answer)->toContain('"¿Tienen turnos el sábado?" · la respondió el asistente');
});

test('conversations lists only the waiting ones when asked', function (): void {
    $business = Business::factory()->create();
    inboundThread($business, 'María López', ConversationStatus::Team);
    inboundThread($business, 'Juan Pérez');

    $answer = (string) (new OwnerConversations($business))->handle(new Request([
        'from' => ownerToday($business), 'to' => ownerToday($business), 'only_waiting' => true,
    ]));

    expect($answer)->toContain('María López')->not->toContain('Juan Pérez');
});

test('an unreadable or impossible date is said back to the model, never guessed', function (string $date): void {
    // Carbon rolled 2026-02-31 into March 3rd: the owner got another day's data.
    $answer = (string) (new OwnerConversations(Business::factory()->create()))->handle(new Request([
        'from' => $date, 'to' => $date, 'only_waiting' => false,
    ]));

    expect($answer)->toContain('Fechas inválidas');
})->with(['hoy', '2026-02-31', '26/09/2026']);

test('birthdays in a window that crosses the new year, with the consent said', function (): void {
    $business = Business::factory()->create();
    Customer::factory()->create(['business_id' => $business->id, 'name' => 'Ana', 'birthday' => '1990-12-30', 'marketing_opt_in_at' => now()]);
    Customer::factory()->create(['business_id' => $business->id, 'name' => 'Lucía', 'birthday' => '1985-01-02']);
    Customer::factory()->create(['business_id' => $business->id, 'name' => 'Carlos', 'birthday' => '1985-06-10']);

    $answer = (string) (new OwnerBirthdays($business))->handle(new Request(['from' => '2026-12-28', 'to' => '2027-01-03']));

    expect($answer)->toContain('cumplen años 2 clientes')
        ->toContain('Ana · miércoles 30/12/2026 · aceptó recibir mensajes')
        ->toContain('Lucía · sábado 02/01/2027 · no aceptó recibir mensajes')
        ->not->toContain('Carlos');
});

test('statistics reads the screen numbers at the plan depth', function (): void {
    $business = Business::factory()->create();
    inboundThread($business, 'María López');

    $answer = (string) (new OwnerStatistics($business))->handle(new Request(['month' => now($business->localTimezone())->format('Y-m')]));

    // A new business trials Negocio: patterns yes, trends (Premium) no.
    expect($answer)->toContain('- Conversaciones: 1 (0)')
        ->toContain(route('statistics'))
        ->not->toContain('Conversaciones por mes');
});

test('plan usage reads the meters of Mi plan', function (): void {
    $answer = (string) (new OwnerPlanUsage(Business::factory()->create()))->handle(new Request);

    expect($answer)->toContain('Plan: '.__('plan.names.negocio'))
        ->toContain('Consultas a este asistente este mes: 0 de 100.');
});

test('the panel guide lists the modules and explains one from its own screen texts', function (): void {
    $guide = new PanelGuide(Business::factory()->create());

    expect((string) $guide->handle(new Request(['module' => 'all'])))->toContain(route('statistics'))
        ->and((string) $guide->handle(new Request(['module' => 'statistics'])))
        ->toContain(__('statistics.kpis.resolution'))
        ->toContain(__('ask.guide.statistics.resolution'));
});

test('the panel guide keeps the base texts under a partial regional file', function (): void {
    app()->setLocale('es_AR');

    expect((string) (new PanelGuide(Business::factory()->create()))->handle(new Request(['module' => 'my-products'])))
        ->toContain('field_price: '.__('client.products.field_price'));
});

test('the panel guide only offers the modules this business has', function (): void {
    $bakery = Business::factory()->create(['appointments_enabled' => false]);
    $salon = Business::factory()->create(['appointments_enabled' => true]);

    expect((string) (new PanelGuide($bakery))->handle(new Request(['module' => 'all'])))
        ->not->toContain(route('agenda'))
        ->and((string) (new PanelGuide($salon))->handle(new Request(['module' => 'all'])))
        ->toContain(route('agenda'))
        ->and((string) (new PanelGuide($salon))->handle(new Request(['module' => 'agenda'])))
        ->toContain(__('agenda.free.title'));
});

test('an impossible month is said back to the model instead of rolling into next year', function (): void {
    $answer = (string) (new OwnerStatistics(Business::factory()->create()))->handle(new Request(['month' => '2026-13']));

    expect($answer)->toContain('Mes inválido');
});

/** A business with the agenda on, on its own clock, and the Monday the tests book on. */
function agendaOwnerBusiness(): Business
{
    return Business::factory()->create([
        'timezone' => 'America/Argentina/Buenos_Aires',
        'appointments_enabled' => true,
        'appointment_slot_minutes' => 30,
    ]);
}

function ownerBookingDay(Business $business): CarbonImmutable
{
    return CarbonImmutable::now($business->localTimezone())->addWeek()->startOfWeek();
}

function ownerBooking(Business $business, CarbonImmutable $startsAt, array $attributes = []): Appointment
{
    return Appointment::factory()->create([
        'business_id' => $business->id,
        'starts_at' => $startsAt->utc(),
        'ends_at' => $startsAt->addMinutes(30)->utc(),
        ...$attributes,
    ]);
}

test('appointments read the day of this business, cancelled ones left out', function (): void {
    $business = agendaOwnerBusiness();
    $day = ownerBookingDay($business);
    $service = Service::factory()->create(['business_id' => $business->id, 'name' => 'Corte de pelo']);
    $customer = Customer::factory()->create(['business_id' => $business->id, 'name' => 'Ana Gómez']);

    ownerBooking($business, $day->setTime(9, 0), ['customer_id' => $customer->id, 'service_id' => $service->id]);
    ownerBooking($business, $day->setTime(10, 0), ['customer_id' => Customer::factory()->create(['business_id' => $business->id, 'name' => 'Juan Pérez'])->id]);
    ownerBooking($business, $day->setTime(11, 0), ['customer_id' => $customer->id, 'status' => AppointmentStatus::Cancelled]);
    ownerBooking(agendaOwnerBusiness(), $day->setTime(9, 0));

    $answer = (string) (new OwnerAppointments($business))->handle(new Request([
        'from' => $day->toDateString(), 'to' => $day->toDateString(),
    ]));

    expect($answer)->toContain('2 turnos.')
        ->toContain('09:00 · Ana Gómez · Corte de pelo (30 min)')
        ->toContain('10:00 · Juan Pérez · turno general (30 min)')
        ->toContain(route('agenda'))
        ->not->toContain('11:00');
});

test('appointments say what happened with a slot only when it is not the confirmed one', function (): void {
    $business = agendaOwnerBusiness();
    $day = ownerBookingDay($business);

    ownerBooking($business, $day->setTime(9, 0), [
        'customer_id' => Customer::factory()->create(['business_id' => $business->id, 'name' => 'Ana Gómez'])->id,
        'status' => AppointmentStatus::NoShow,
    ]);
    ownerBooking($business, $day->setTime(10, 0), [
        'customer_id' => Customer::factory()->create(['business_id' => $business->id, 'name' => 'Juan Pérez'])->id,
    ]);

    $answer = (string) (new OwnerAppointments($business))->handle(new Request([
        'from' => $day->toDateString(), 'to' => $day->toDateString(),
    ]));

    expect($answer)->toContain('Ana Gómez · turno general (30 min) · no vino')
        ->toContain('Juan Pérez · turno general (30 min)')
        ->not->toContain('confirmado');
});

test('an empty day is said as empty, with the screen that shows it', function (): void {
    $business = agendaOwnerBusiness();
    $day = ownerBookingDay($business);

    $answer = (string) (new OwnerAppointments($business))->handle(new Request([
        'from' => $day->toDateString(), 'to' => $day->toDateString(),
    ]));

    expect($answer)->toContain('no hay ningún turno reservado')->toContain(route('agenda'));
});

test('an appointment carries the chat where it was booked, so she can write back', function (): void {
    $business = agendaOwnerBusiness();
    $day = ownerBookingDay($business);
    $thread = Conversation::factory()->create(['business_id' => $business->id]);
    $customer = Customer::factory()->create(['business_id' => $business->id, 'name' => 'Ana Gómez']);

    ownerBooking($business, $day->setTime(9, 0), ['customer_id' => $customer->id, 'conversation_id' => $thread->id]);
    ownerBooking($business, $day->setTime(10, 0), ['customer_id' => $customer->id]);

    $answer = (string) (new OwnerAppointments($business))->handle(new Request([
        'from' => $day->toDateString(), 'to' => $day->toDateString(),
    ]));

    // The second one was booked by the owner herself: no thread to offer.
    expect($answer)->toContain('09:00 · Ana Gómez · turno general (30 min) · charla '.route('conversations', ['hilo' => $thread->id]))
        ->toContain('10:00 · Ana Gómez · turno general (30 min)'."\n");
});

test('free slots read the day left over after what is already booked', function (): void {
    $business = agendaOwnerBusiness();
    $day = ownerBookingDay($business);

    foreach (range(1, 5) as $weekday) {
        $business->hours()->create(['day_of_week' => $weekday, 'opens_at' => '09:00', 'closes_at' => '11:00']);
    }

    ownerBooking($business, $day->setTime(9, 0), [
        'customer_id' => Customer::factory()->create(['business_id' => $business->id])->id,
    ]);

    $answer = (string) (new OwnerFreeSlots($business))->handle(new Request(['date' => $day->toDateString()]));

    // 09:00-11:00 in slots of 30 min, minus the 09:00 already taken.
    expect($answer)->toContain('3 horas libres el '.mb_strtolower($day->locale('es')->translatedFormat('l j/n')))
        ->toContain('09:30')->toContain('10:30')->not->toContain('09:00,')
        ->and($answer)->toContain(route('agenda'));
});

test('free slots say the next ones when no day is asked, and never a bare no', function (): void {
    $business = agendaOwnerBusiness();
    $day = ownerBookingDay($business);

    foreach (range(1, 5) as $weekday) {
        $business->hours()->create(['day_of_week' => $weekday, 'opens_at' => '09:00', 'closes_at' => '11:00']);
    }

    expect((string) (new OwnerFreeSlots($business))->handle(new Request(['date' => null])))
        ->toContain('Próximas horas libres')
        // A Sunday is closed: the answer says which of the two it is.
        ->and((string) (new OwnerFreeSlots($business))->handle(new Request(['date' => $day->addDays(6)->toDateString()])))
        ->toContain('No queda ninguna hora libre')
        ->toContain('el día esté completo o que el negocio no abra');
});

test('a business that gives no slots is never handed the agenda skills', function (): void {
    $bakery = Business::factory()->create(['appointments_enabled' => false]);
    $salon = agendaOwnerBusiness();

    expect(OwnerAppointments::forOwner(new AskAtendia($bakery, 'Ana')))->toBeNull()
        ->and(OwnerFreeSlots::forOwner(new AskAtendia($bakery, 'Ana')))->toBeNull()
        ->and(OwnerAppointments::forOwner(new AskAtendia($salon, 'Ana')))->toBeInstanceOf(OwnerAppointments::class)
        ->and(OwnerFreeSlots::forOwner(new AskAtendia($salon, 'Ana')))->toBeInstanceOf(OwnerFreeSlots::class);
});
