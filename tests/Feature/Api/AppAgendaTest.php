<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use App\Services\Tenant;
use Carbon\CarbonImmutable;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

uses(DatabaseTransactions::class);

/*
|--------------------------------------------------------------------------
| "Agenda" over the API
|--------------------------------------------------------------------------
| The phone reads the web's own day and week and writes through the Actions the web
| calls: the words, the free hours and the checks are the web's, and a booking of
| another business is never reachable. Nothing here writes to the customer.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
    Queue::fake();
    Http::fake();
});

/** An owner whose business opens 09:00-12:00 on weekdays, in 30-minute slots, with the agenda on. */
function agendaApp(array $business = []): User
{
    $owner = payingUser();
    $owner->business->forceFill(['timezone' => 'America/Argentina/Buenos_Aires', 'appointments_enabled' => true, 'appointment_slot_minutes' => 30, ...$business])->save();

    foreach (range(1, 5) as $weekday) {
        $owner->business->hours()->create(['day_of_week' => $weekday, 'opens_at' => '09:00', 'closes_at' => '12:00']);
    }

    return $owner->refresh();
}

function nextAgendaMonday(User $owner): CarbonImmutable
{
    return CarbonImmutable::now($owner->business->localTimezone())->addWeek()->startOfWeek();
}

function asAgendaApp(User $user): TestCase
{
    app('auth')->forgetGuards();

    return test()->withToken(appInstallToken($user))->withHeaders(apiDeviceHeaders());
}

/** @param  array<string, mixed>  $attributes */
function bookingOf(User $owner, CarbonImmutable $at, array $attributes = []): Appointment
{
    static $seen = 0;

    return Appointment::factory()->create([
        'business_id' => $owner->business_id,
        'customer_id' => Customer::factory()->create(['business_id' => $owner->business_id, 'name' => 'Ana Gómez', 'phone' => '54911555501'.sprintf('%02d', ++$seen)])->id,
        'starts_at' => $at->utc(),
        'ends_at' => $at->addMinutes(30)->utc(),
        ...$attributes,
    ]);
}

describe('the day', function (): void {
    it('paints the bookings in the web\'s words and the hours still free', function (): void {
        $owner = agendaApp();
        $monday = nextAgendaMonday($owner);
        $service = Service::factory()->create(['business_id' => $owner->business_id, 'name' => 'Corte', 'is_bookable' => true, 'service_type_id' => null]);
        bookingOf($owner, $monday->setTime(9, 0), ['service_id' => $service->id, 'notes' => 'Con tinte']);
        bookingOf($owner, $monday->setTime(10, 0), ['status' => AppointmentStatus::Done]);

        $data = asAgendaApp($owner)->getJson('/api/v1/agenda?day='.$monday->format('Y-m-d'))->assertOk()->json('data');
        $first = $data['bookings'][0];

        expect($data['isOn'])->toBeTrue()
            ->and($data['view'])->toBe('day')
            ->and($data['heading'])->toBe(ucfirst($monday->locale('es')->translatedFormat('l j \d\e F')))
            ->and($data['count'])->toBe(2)
            ->and($first['time'])->toBe('09:00')
            ->and($first['name'])->toBe('Ana Gómez')
            ->and($first['service'])->toBe('Corte')
            ->and($first['minutes'])->toBe('30 min')
            ->and($first['phone'])->toStartWith('+54911555501')
            ->and($first['notes'])->toBe('Con tinte')
            ->and($first['state'])->toBeNull()
            ->and($first['open'])->toBeTrue()
            ->and($first['cancelMessage'])->toBe(__('agenda.cancel_message', ['when' => '09:00']))
            ->and($data['bookings'][1]['state'])->toBe(__('agenda.status.done'))
            ->and($data['bookings'][1]['open'])->toBeFalse()
            ->and($data['bookings'][1]['service'])->toBe(__('agenda.plain_slot'))
            // 09:00 is taken; 10:00 is a done booking, still holding its hour.
            ->and($data['hours'])->toBe(['09:30', '10:30', '11:00', '11:30'])
            ->and($data['copy']['book'])->toBe(__('agenda.book'))
            ->and($data['copy']['free']['title'])->toBe(__('agenda.free.title'));
    });

    it('opens on today in the business\'s own timezone when no day is asked', function (): void {
        $owner = agendaApp();

        $data = asAgendaApp($owner)->getJson('/api/v1/agenda')->assertOk()->json('data');

        expect($data['day'])->toBe(CarbonImmutable::now($owner->business->localTimezone())->format('Y-m-d'))->and($data['today'])->toBe($data['day']);
    });

    it('says the empty day in the web\'s words', function (): void {
        $owner = agendaApp();

        $data = asAgendaApp($owner)->getJson('/api/v1/agenda?day='.nextAgendaMonday($owner)->format('Y-m-d'))->assertOk()->json('data');

        expect($data['bookings'])->toBe([])->and($data['copy']['empty']['title'])->toBe(__('agenda.empty.title'));
    });

    it('speaks "cita" to a Venezuelan business, as the web does', function (): void {
        $owner = agendaApp();

        $data = asAgendaApp($owner)->withHeader('Accept-Language', 'es-VE')->getJson('/api/v1/agenda')->assertOk()->json('data');

        expect($data['copy']['book'])->toBe('Reservar una cita')->and($data['copy']['empty']['title'])->toBe('No hay citas este día');
    });

    it('refuses a day or a view the web does not have', function (): void {
        $owner = agendaApp();

        asAgendaApp($owner)->getJson('/api/v1/agenda?day=26/10/2026')->assertUnprocessable()->assertJsonValidationErrors('day');
        asAgendaApp($owner)->getJson('/api/v1/agenda?view=month')->assertUnprocessable()->assertJsonValidationErrors('view');
    });
});

describe('the week', function (): void {
    it('lists the seven days Monday first with each one\'s bookings, free hours and closed days', function (): void {
        $owner = agendaApp();
        $monday = nextAgendaMonday($owner);
        bookingOf($owner, $monday->addDay()->setTime(9, 30));

        $data = asAgendaApp($owner)->getJson('/api/v1/agenda?view=week&day='.$monday->addDays(2)->format('Y-m-d'))->assertOk()->json('data');
        $days = collect($data['week']);

        expect($data['heading'])->toBe(__('agenda.week_of', ['day' => $monday->format('j/n')]))
            ->and($days)->toHaveCount(7)
            ->and($days->pluck('date')->first())->toBe($monday->format('Y-m-d'))
            ->and($days[1]['bookings'])->toBe([['time' => '09:30', 'name' => 'Ana Gómez']])
            ->and($days[0]['empty'])->toBe(__('agenda.week_none'))
            ->and($days[5]['empty'])->toBe(__('agenda.week_closed'))
            ->and($days[0]['free'])->toBe(trans_choice('agenda.week_free', 6, ['count' => 6]))
            ->and($days[1]['free'])->toBe(trans_choice('agenda.week_free', 5, ['count' => 5]))
            ->and($days[2]['current'])->toBeTrue()
            ->and($data['count'])->toBe(1)
            ->and($data['bookings'])->toBe([])->and($data['hours'])->toBe([]);
    });
});

describe('the agenda switched off', function (): void {
    it('says why there are no hours instead of painting a day that blames the day for it', function (): void {
        $owner = agendaApp(['appointments_enabled' => false]);

        $data = asAgendaApp($owner)->getJson('/api/v1/agenda')->assertOk()->json('data');

        expect($data['isOn'])->toBeFalse()
            ->and($data['copy']['off']['title'])->toBe(__('agenda.off.title'))
            ->and($data['copy']['off']['cta'])->toBe(__('agenda.off.cta'))
            ->and($data)->not->toHaveKey('bookings');
    });
});

describe('the sheet', function (): void {
    it('opens a new booking on a day, with the hour a free-hour chip picked and the services that take a booking', function (): void {
        $owner = agendaApp();
        $monday = nextAgendaMonday($owner);
        Service::factory()->create(['business_id' => $owner->business_id, 'name' => 'Corte', 'is_bookable' => true, 'service_type_id' => null]);
        Service::factory()->create(['business_id' => $owner->business_id, 'name' => 'Lavado', 'is_bookable' => false, 'service_type_id' => null]);
        $ana = Customer::factory()->create(['business_id' => $owner->business_id, 'name' => 'Ana Gómez']);

        $data = asAgendaApp($owner)->getJson('/api/v1/agenda/sheet?day='.$monday->format('Y-m-d').'&time=10:00')->assertOk()->json('data');

        expect($data['sheet']['moving'])->toBeFalse()
            ->and($data['sheet']['title'])->toBe(__('agenda.form.title'))
            ->and($data['sheet']['save'])->toBe(__('agenda.form.save'))
            ->and($data['sheet']['values']['time'])->toBe('10:00')
            ->and(collect($data['sheet']['services'])->pluck('name')->all())->toBe(['Corte'])
            ->and($data['sheet']['customers'])->toBe([['id' => $ana->id, 'name' => 'Ana Gómez']])
            ->and($data['sheet']['hours'])->toBe(['09:00', '09:30', '10:00', '10:30', '11:00', '11:30'])
            ->and($data['copy']['form']['customer'])->toBe(__('agenda.form.customer'));
    });

    it('opens a booking to move it, keeping its own hour among the offered ones', function (): void {
        $owner = agendaApp();
        $monday = nextAgendaMonday($owner);
        $booking = bookingOf($owner, $monday->setTime(9, 30));

        $sheet = asAgendaApp($owner)->getJson('/api/v1/agenda/sheet?day='.$monday->format('Y-m-d').'&booking='.$booking->id)->assertOk()->json('data.sheet');

        expect($sheet['moving'])->toBeTrue()
            ->and($sheet['title'])->toBe(__('agenda.form.move_title'))
            ->and($sheet['save'])->toBe(__('agenda.form.move_save'))
            ->and($sheet['customer'])->toBe('Ana Gómez')
            ->and($sheet['values']['time'])->toBe('09:30')
            ->and($sheet['hours'])->toContain('09:30');
    });

    it('offers only the hours a longer service fits in', function (): void {
        $owner = agendaApp();
        $monday = nextAgendaMonday($owner);
        $long = Service::factory()->create(['business_id' => $owner->business_id, 'is_bookable' => true, 'service_type_id' => null, 'duration_minutes' => 90]);
        bookingOf($owner, $monday->setTime(10, 30));

        $plain = asAgendaApp($owner)->getJson('/api/v1/agenda/hours?day='.$monday->format('Y-m-d'))->json('data.hours');
        $fitting = asAgendaApp($owner)->getJson('/api/v1/agenda/hours?day='.$monday->format('Y-m-d').'&service='.$long->id)->json('data.hours');

        // 09:30 is free for a plain slot but a 90-minute service would run into the 10:30 booking.
        expect($plain)->toContain('09:30')->and($fitting)->not->toContain('09:30')->and($fitting)->toContain('09:00');
    });

    it('answers the free hours of another day for the picker that changes its day', function (): void {
        $owner = agendaApp();
        $monday = nextAgendaMonday($owner);
        bookingOf($owner, $monday->setTime(9, 0));

        $hours = asAgendaApp($owner)->getJson('/api/v1/agenda/hours?day='.$monday->format('Y-m-d'))->assertOk()->json('data.hours');
        $kept = asAgendaApp($owner)->getJson('/api/v1/agenda/hours?day='.$monday->format('Y-m-d').'&keep=09:00')->assertOk()->json('data.hours');

        expect($hours)->not->toContain('09:00')->and($kept)->toContain('09:00');
    });
});

describe('booking', function (): void {
    it('books a customer in a free hour as the web\'s sheet does', function (): void {
        $owner = agendaApp();
        $monday = nextAgendaMonday($owner);
        $customer = Customer::factory()->create(['business_id' => $owner->business_id, 'name' => 'Ana Gómez']);
        $service = Service::factory()->create(['business_id' => $owner->business_id, 'is_bookable' => true, 'service_type_id' => null]);

        asAgendaApp($owner)->postJson('/api/v1/agenda/bookings', [
            'customer_id' => $customer->id, 'service_id' => $service->id, 'day' => $monday->format('Y-m-d'), 'time' => '10:00', 'notes' => 'Primera vez',
        ])->assertCreated()->assertJsonPath('message', __('agenda.notify.booked'))->assertJsonPath('data.day', $monday->format('Y-m-d'));

        $booking = $owner->business->appointments()->sole();

        expect($booking->customer_id)->toBe($customer->id)
            ->and($booking->service_id)->toBe($service->id)
            ->and($booking->status)->toBe(AppointmentStatus::Confirmed)
            ->and($booking->notes)->toBe('Primera vez')
            ->and($booking->starts_at->setTimezone($owner->business->localTimezone())->format('Y-m-d H:i'))->toBe($monday->format('Y-m-d').' 10:00');
    });

    it('says the hour is gone in the web\'s words when someone took it meanwhile', function (): void {
        $owner = agendaApp();
        $monday = nextAgendaMonday($owner);
        $customer = Customer::factory()->create(['business_id' => $owner->business_id]);
        bookingOf($owner, $monday->setTime(10, 0));

        asAgendaApp($owner)->postJson('/api/v1/agenda/bookings', ['customer_id' => $customer->id, 'day' => $monday->format('Y-m-d'), 'time' => '10:00'])
            ->assertUnprocessable()->assertJsonPath('errors.time.0', __('agenda.notify.slot_taken'));

        expect($owner->business->appointments()->count())->toBe(1);
    });

    it('refuses what the web\'s sheet refuses', function (array $body, string $field): void {
        $owner = agendaApp();
        $monday = nextAgendaMonday($owner);
        $mine = Customer::factory()->create(['business_id' => $owner->business_id]);
        $foreign = Customer::factory()->create(['business_id' => payingUser()->business_id]);
        $notBookable = Service::factory()->create(['business_id' => $owner->business_id, 'is_bookable' => false, 'service_type_id' => null]);
        $body = collect($body)->map(fn (mixed $value): mixed => match ($value) {
            ':foreign' => $foreign->id, ':not-bookable' => $notBookable->id, default => $value,
        })->all();

        asAgendaApp($owner)->postJson('/api/v1/agenda/bookings', ['customer_id' => $mine->id, 'day' => $monday->format('Y-m-d'), 'time' => '10:00', ...$body])
            ->assertUnprocessable()->assertJsonValidationErrors($field);

        expect($owner->business->appointments()->count())->toBe(0);
    })->with([
        'no customer' => [['customer_id' => null], 'customer_id'],
        'a customer of another business' => [['customer_id' => ':foreign'], 'customer_id'],
        'a service that takes no booking' => [['service_id' => ':not-bookable'], 'service_id'],
        'a day that is not a date' => [['day' => '26/10/2026'], 'day'],
        'no hour' => [['time' => null], 'time'],
        'an hour that is not one' => [['time' => '25:99'], 'time'],
        'a note past the limit' => [['notes' => str_repeat('a', 256)], 'notes'],
        'a note with a script' => [['notes' => '<script>alert(1)</script>'], 'notes'],
    ]);

    it('refuses a deleted service and reads a boolean as nothing, never as customer 1', function (): void {
        $owner = agendaApp();
        $monday = nextAgendaMonday($owner);
        $customer = Customer::factory()->create(['business_id' => $owner->business_id]);
        $gone = Service::factory()->create(['business_id' => $owner->business_id, 'is_bookable' => true, 'service_type_id' => null]);
        $gone->delete();

        asAgendaApp($owner)->postJson('/api/v1/agenda/bookings', ['customer_id' => $customer->id, 'service_id' => $gone->id, 'day' => $monday->format('Y-m-d'), 'time' => '10:00'])
            ->assertUnprocessable()->assertJsonValidationErrors('service_id');
        asAgendaApp($owner)->postJson('/api/v1/agenda/bookings', ['customer_id' => true, 'day' => $monday->format('Y-m-d'), 'time' => '10:00'])
            ->assertUnprocessable()->assertJsonValidationErrors('customer_id');
    });

    it('reads a body that is not text as nothing sent, never a crash', function (): void {
        $owner = agendaApp();

        asAgendaApp($owner)->postJson('/api/v1/agenda/bookings', ['customer_id' => ['x'], 'day' => ['y'], 'time' => ['z'], 'notes' => ['n']])
            ->assertUnprocessable()->assertJsonValidationErrors(['customer_id', 'day', 'time']);
    });
});

describe('moving, cancelling and marking', function (): void {
    it('moves a booking to another hour and touches nothing else', function (): void {
        $owner = agendaApp();
        $monday = nextAgendaMonday($owner);
        $booking = bookingOf($owner, $monday->setTime(9, 0), ['notes' => 'Con tinte']);
        $other = Customer::factory()->create(['business_id' => $owner->business_id]);

        asAgendaApp($owner)->putJson("/api/v1/agenda/bookings/{$booking->id}", [
            'day' => $monday->addDay()->format('Y-m-d'), 'time' => '11:00', 'customer_id' => $other->id, 'notes' => 'Otra',
        ])->assertOk()->assertJsonPath('message', __('agenda.notify.moved'));

        $booking->refresh();

        expect($booking->starts_at->setTimezone($owner->business->localTimezone())->format('Y-m-d H:i'))->toBe($monday->addDay()->format('Y-m-d').' 11:00')
            ->and($booking->customer_id)->not->toBe($other->id)
            ->and($booking->notes)->toBe('Con tinte');
    });

    it('says the new hour is gone when it is not free', function (): void {
        $owner = agendaApp();
        $monday = nextAgendaMonday($owner);
        $booking = bookingOf($owner, $monday->setTime(9, 0));
        bookingOf($owner, $monday->setTime(10, 0));

        asAgendaApp($owner)->putJson("/api/v1/agenda/bookings/{$booking->id}", ['day' => $monday->format('Y-m-d'), 'time' => '10:00'])
            ->assertUnprocessable()->assertJsonPath('errors.time.0', __('agenda.notify.slot_taken'));

        expect($booking->refresh()->starts_at->setTimezone($owner->business->localTimezone())->format('H:i'))->toBe('09:00');
    });

    it('cancels a booking and frees its hour, without writing to the customer', function (): void {
        $owner = agendaApp();
        $monday = nextAgendaMonday($owner);
        $booking = bookingOf($owner, $monday->setTime(9, 0));

        asAgendaApp($owner)->postJson("/api/v1/agenda/bookings/{$booking->id}/cancel")->assertOk()->assertJsonPath('message', __('agenda.notify.cancelled'));

        expect($booking->refresh()->status)->toBe(AppointmentStatus::Cancelled);
        Http::assertNothingSent();

        $hours = asAgendaApp($owner)->getJson('/api/v1/agenda?day='.$monday->format('Y-m-d'))->json('data.hours');

        expect($hours)->toContain('09:00');
    });

    it('marks a booking as attended', function (): void {
        $owner = agendaApp();
        $booking = bookingOf($owner, nextAgendaMonday($owner)->setTime(9, 0));

        asAgendaApp($owner)->postJson("/api/v1/agenda/bookings/{$booking->id}/done")->assertOk()->assertJsonPath('message', __('agenda.notify.marked'));

        expect($booking->refresh()->status)->toBe(AppointmentStatus::Done);
    });

    it('does nothing on a booking that no longer stands, as the web offers nothing there', function (): void {
        $owner = agendaApp();
        $monday = nextAgendaMonday($owner);
        $done = bookingOf($owner, $monday->setTime(9, 0), ['status' => AppointmentStatus::Done]);
        $cancelled = bookingOf($owner, $monday->setTime(10, 0), ['status' => AppointmentStatus::Cancelled]);

        asAgendaApp($owner)->postJson("/api/v1/agenda/bookings/{$done->id}/cancel")->assertNotFound();
        asAgendaApp($owner)->postJson("/api/v1/agenda/bookings/{$cancelled->id}/done")->assertNotFound();
        asAgendaApp($owner)->putJson("/api/v1/agenda/bookings/{$done->id}", ['day' => $monday->format('Y-m-d'), 'time' => '11:00'])->assertNotFound();
        asAgendaApp($owner)->getJson('/api/v1/agenda/sheet?day='.$monday->format('Y-m-d')."&booking={$done->id}")->assertNotFound();
        // The refusal speaks, so the phone never shows an empty error.
        asAgendaApp($owner)->postJson("/api/v1/agenda/bookings/{$done->id}/cancel")->assertJsonPath('message', __('notifications.not_found'));

        expect($done->refresh()->status)->toBe(AppointmentStatus::Done)->and($cancelled->refresh()->status)->toBe(AppointmentStatus::Cancelled);
    });

    it('does not reach the booking of another business', function (): void {
        $owner = agendaApp();
        $foreignOwner = agendaApp();
        $foreign = bookingOf($foreignOwner, nextAgendaMonday($foreignOwner)->setTime(9, 0));
        $day = nextAgendaMonday($owner)->format('Y-m-d');

        asAgendaApp($owner)->putJson("/api/v1/agenda/bookings/{$foreign->id}", ['day' => $day, 'time' => '11:00'])->assertNotFound();
        asAgendaApp($owner)->postJson("/api/v1/agenda/bookings/{$foreign->id}/cancel")->assertNotFound();
        asAgendaApp($owner)->postJson("/api/v1/agenda/bookings/{$foreign->id}/done")->assertNotFound();
        asAgendaApp($owner)->getJson("/api/v1/agenda/sheet?day={$day}&booking={$foreign->id}")->assertNotFound();

        expect(app(Tenant::class)->for($foreignOwner->business_id, fn () => $foreign->refresh()->status))->toBe(AppointmentStatus::Confirmed);
    });
});

describe('who may', function (): void {
    it('is closed to an agent', function (): void {
        $owner = agendaApp();
        $agent = User::factory()->create(['business_id' => $owner->business_id]);
        $agent->syncRoles(['agent']);

        asAgendaApp($agent)->getJson('/api/v1/agenda')->assertForbidden();
        asAgendaApp($agent)->postJson('/api/v1/agenda/bookings', [])->assertForbidden();
    });

    it('is closed to a call without a token', function (): void {
        test()->withHeaders(apiDeviceHeaders())->getJson('/api/v1/agenda')->assertUnauthorized();
    });
});
