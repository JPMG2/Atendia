<?php

declare(strict_types=1);

use App\Ai\Agents\AsistenteAtendia;
use App\Ai\Tools\BookCustomerAppointment;
use App\Ai\Tools\CheckFreeSlots;
use App\Ai\Tools\ManageCustomerAppointments;
use App\Ai\Tools\OwnerAppointments;
use App\Ai\Tools\OwnerFreeSlots;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\Customer;
use App\Models\ProfessionalHour;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The assistant and who attends
|--------------------------------------------------------------------------
| Nobody named: whoever is free takes it. Somebody named: their hours, and the
| answer says with whom. The tools answer data, and a business with nobody set
| up reads nothing new.
*/

/** A business open 09:00-12:00 on weekdays with the agenda on, a customer and a corte. */
function whoContext(): array
{
    $business = Business::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires', 'appointments_enabled' => true, 'appointment_slot_minutes' => 30]);

    foreach (range(1, 5) as $weekday) {
        $business->hours()->create(['day_of_week' => $weekday, 'opens_at' => '09:00', 'closes_at' => '12:00']);
    }

    $cut = $business->services()->create(['name' => 'Corte', 'duration_minutes' => 30, 'is_bookable' => true]);

    return [$business, Customer::factory()->create(['business_id' => $business->id, 'name' => 'Marta']), $cut];
}

function whoMondayAt(Business $business, string $time = '09:00'): CarbonImmutable
{
    [$hour, $minute] = array_map('intval', explode(':', $time));

    return CarbonImmutable::now($business->localTimezone())->addWeek()->startOfWeek()->setTime($hour, $minute);
}

function whoAsk(array $input): Request
{
    return new Request($input);
}

test('a business with nobody set up hears nothing about people', function (): void {
    [$business] = whoContext();
    $monday = whoMondayAt($business);

    $answer = (string) (new CheckFreeSlots($business))->handle(whoAsk(['date' => $monday->format('Y-m-d')]));

    expect($answer)->not->toContain('Atienden')
        ->and((string) (new AsistenteAtendia($business))->instructions())->not->toContain('"professional"');
});

test('with people set up the assistant is told it may choose one, only when asked', function (): void {
    [$business] = whoContext();
    $business->professionals()->create(['name' => 'Ana Soto']);

    expect((string) (new AsistenteAtendia($business))->instructions())->toContain('"professional"');
});

test('free hours say who attends, so the customer can ask for one', function (): void {
    [$business, , $cut] = whoContext();
    $business->professionals()->create(['name' => 'Ana Soto', 'sort_order' => 1])->services()->attach($cut->id);
    $business->professionals()->create(['name' => 'Luis Paz', 'sort_order' => 2])->services()->attach($cut->id);

    $answer = (string) (new CheckFreeSlots($business))->handle(whoAsk(['date' => whoMondayAt($business)->format('Y-m-d'), 'service' => 'corte']));

    expect($answer)->toContain('09:00')->toContain('duran 30 min')->toContain('Atienden: Ana Soto, Luis Paz.');
});

test('asking for a person gives their own hours, with their own length, and says so', function (): void {
    [$business, , $cut] = whoContext();
    $ana = $business->professionals()->create(['name' => 'Ana Soto']);
    $ana->services()->attach($cut->id, ['duration_minutes' => 45]);
    $business->professionals()->create(['name' => 'Luis Paz'])->services()->attach($cut->id);
    ProfessionalHour::factory()->create(['business_id' => $business->id, 'professional_id' => $ana->id, 'day_of_week' => 1, 'opens_at' => '09:00', 'closes_at' => '10:30']);
    $day = whoMondayAt($business)->format('Y-m-d');

    $mine = (string) (new CheckFreeSlots($business))->handle(whoAsk(['date' => $day, 'service' => 'corte', 'professional' => 'ana']));
    $anyone = (string) (new CheckFreeSlots($business))->handle(whoAsk(['date' => $day, 'service' => 'corte']));

    expect($mine)->toContain('con Ana Soto')->toContain('duran 45 min')->toContain('09:00')->not->toContain('11:00')->not->toContain('Atienden')
        ->and($anyone)->toContain('duran entre 30 y 45 min');
});

test('a name nobody has is answered with who does attend, never with someone else', function (): void {
    [$business, , $cut] = whoContext();
    $business->professionals()->create(['name' => 'Ana Soto'])->services()->attach($cut->id);

    $answer = (string) (new CheckFreeSlots($business))->handle(whoAsk(['date' => whoMondayAt($business)->format('Y-m-d'), 'service' => 'corte', 'professional' => 'Roberto']));

    expect($answer)->toContain('No hay nadie con ese nombre')->toContain('Atienden: Ana Soto.');
});

test('a name matches loosely, like a service: case and accents do not matter', function (): void {
    [$business, , $cut] = whoContext();
    $luis = $business->professionals()->create(['name' => 'Luis Páz']);
    $luis->services()->attach($cut->id);
    $monday = whoMondayAt($business);

    (new BookCustomerAppointment($business, $business->customers()->sole()))->handle(whoAsk(['starts_at' => $monday->format('Y-m-d H:i'), 'service' => 'corte', 'professional' => 'LUIS PAZ']));

    expect(Appointment::sole()->professional_id)->toBe($luis->id);
});

test('booking with nobody named lets the first free person take it, and the answer says who', function (): void {
    [$business, $customer, $cut] = whoContext();
    $ana = $business->professionals()->create(['name' => 'Ana Soto', 'sort_order' => 1]);
    $ana->services()->attach($cut->id);
    $business->professionals()->create(['name' => 'Luis Paz', 'sort_order' => 2])->services()->attach($cut->id);
    $tool = new BookCustomerAppointment($business, $customer);
    $at = whoMondayAt($business)->format('Y-m-d H:i');

    $first = (string) $tool->handle(whoAsk(['starts_at' => $at, 'service' => 'corte']));
    $second = (string) $tool->handle(whoAsk(['starts_at' => $at, 'service' => 'corte']));
    $third = (string) $tool->handle(whoAsk(['starts_at' => $at, 'service' => 'corte']));

    expect($first)->toContain('Turno reservado')->toContain('con Ana Soto')
        ->and($second)->toContain('con Luis Paz')
        ->and($third)->toContain('ya no está libre');
});

test('booking a named person who is taken lists that person\'s other hours', function (): void {
    [$business, $customer, $cut] = whoContext();
    $ana = $business->professionals()->create(['name' => 'Ana Soto']);
    $ana->services()->attach($cut->id);
    $business->professionals()->create(['name' => 'Luis Paz'])->services()->attach($cut->id);
    $tool = new BookCustomerAppointment($business, $customer);
    $at = whoMondayAt($business)->format('Y-m-d H:i');

    $tool->handle(whoAsk(['starts_at' => $at, 'service' => 'corte', 'professional' => 'ana']));
    $again = (string) $tool->handle(whoAsk(['starts_at' => $at, 'service' => 'corte', 'professional' => 'ana']));

    expect($again)->toContain('ya no está libre')->toContain('09:30')->not->toContain('09:00 ');
});

test('booking someone who does not attend the service, or does not exist, is refused', function (): void {
    [$business, $customer, $cut] = whoContext();
    $business->services()->create(['name' => 'Color', 'duration_minutes' => 30, 'is_bookable' => true]);
    $business->professionals()->create(['name' => 'Ana Soto'])->services()->attach($cut->id);
    $tool = new BookCustomerAppointment($business, $customer);
    $at = whoMondayAt($business)->format('Y-m-d H:i');

    expect((string) $tool->handle(whoAsk(['starts_at' => $at, 'service' => 'color', 'professional' => 'ana'])))->toContain('No hay nadie con ese nombre')
        ->and((string) $tool->handle(whoAsk(['starts_at' => $at, 'service' => 'corte', 'professional' => 'zzz'])))->toContain('No hay nadie con ese nombre')
        ->and(Appointment::count())->toBe(0);
});

test('the owner can ask for one person\'s free hours and sees with whom each booking is', function (): void {
    [$business, $customer, $cut] = whoContext();
    $ana = $business->professionals()->create(['name' => 'Ana Soto']);
    $ana->services()->attach($cut->id);
    $business->professionals()->create(['name' => 'Luis Paz'])->services()->attach($cut->id);
    ProfessionalHour::factory()->create(['business_id' => $business->id, 'professional_id' => $ana->id, 'day_of_week' => 1, 'opens_at' => '09:00', 'closes_at' => '10:00']);
    $monday = whoMondayAt($business);
    Appointment::factory()->create(['business_id' => $business->id, 'customer_id' => $customer->id, 'service_id' => $cut->id, 'professional_id' => $ana->id, 'starts_at' => $monday->utc(), 'ends_at' => $monday->addMinutes(30)->utc()]);

    $free = (string) (new OwnerFreeSlots($business))->handle(whoAsk(['date' => $monday->format('Y-m-d'), 'professional' => 'ana']));
    $unknown = (string) (new OwnerFreeSlots($business))->handle(whoAsk(['date' => $monday->format('Y-m-d'), 'professional' => 'zzz']));
    $booked = (string) (new OwnerAppointments($business))->handle(whoAsk(['from' => $monday->format('Y-m-d'), 'to' => $monday->format('Y-m-d')]));

    expect($free)->toContain('con Ana Soto')->toContain('09:30')->not->toContain('11:00')
        ->and($unknown)->toContain('No hay nadie con ese nombre')
        ->and($booked)->toContain('Corte con Ana Soto');
});

test('the customer\'s own list says with whom each booking is, and moving offers only that person\'s hours', function (): void {
    [$business, $customer, $cut] = whoContext();
    $ana = $business->professionals()->create(['name' => 'Ana Soto']);
    $ana->services()->attach($cut->id);
    ProfessionalHour::factory()->create(['business_id' => $business->id, 'professional_id' => $ana->id, 'day_of_week' => 1, 'opens_at' => '09:00', 'closes_at' => '10:00']);
    $monday = whoMondayAt($business);
    (new BookCustomerAppointment($business, $customer))->handle(whoAsk(['starts_at' => $monday->format('Y-m-d H:i'), 'service' => 'corte', 'professional' => 'ana']));
    $manage = new ManageCustomerAppointments($business, $customer);

    $list = (string) $manage->handle(whoAsk(['action' => 'list']));
    $refused = (string) $manage->handle(whoAsk(['action' => 'move', 'new_starts_at' => whoMondayAt($business, '11:00')->format('Y-m-d H:i')]));
    $moved = (string) $manage->handle(whoAsk(['action' => 'move', 'new_starts_at' => whoMondayAt($business, '09:30')->format('Y-m-d H:i')]));

    expect($list)->toContain('con Ana Soto')
        ->and($refused)->toContain('Ese horario no está libre')->toContain('09:30')->not->toContain('11:00')
        ->and($moved)->toContain('Turno movido')->toContain('con Ana Soto')
        ->and(Appointment::sole()->professional_id)->toBe($ana->id);
});
