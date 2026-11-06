<?php

declare(strict_types=1);

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\ProfessionalHour;
use App\Models\User;
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
| Who attends, in the Agenda over the API
|--------------------------------------------------------------------------
| The phone's booking sheet says what the web's says: who can take the service,
| their own hours, and with whom each booking is. The rules are the web's.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
    Queue::fake();
    Http::fake();
});

/** An owner open 09:00-12:00 on weekdays with the agenda on, a customer and a service. */
function whoApp(): User
{
    $owner = payingUser();
    $owner->business->forceFill(['timezone' => 'America/Argentina/Buenos_Aires', 'appointments_enabled' => true, 'appointment_slot_minutes' => 30])->save();

    foreach (range(1, 5) as $weekday) {
        $owner->business->hours()->create(['day_of_week' => $weekday, 'opens_at' => '09:00', 'closes_at' => '12:00']);
    }

    Customer::factory()->create(['business_id' => $owner->business_id, 'name' => 'Marta Gil', 'phone' => '5491155550199']);
    $owner->business->services()->create(['name' => 'Corte', 'duration_minutes' => 30, 'is_bookable' => true]);
    $owner->business->services()->create(['name' => 'Color', 'duration_minutes' => 30, 'is_bookable' => true]);

    return $owner->refresh();
}

function whoMonday(User $owner): CarbonImmutable
{
    return CarbonImmutable::now($owner->business->localTimezone())->addWeek()->startOfWeek();
}

function asWhoApp(User $user): TestCase
{
    app('auth')->forgetGuards();

    return test()->withToken(appInstallToken($user))->withHeaders(apiDeviceHeaders());
}

it('adds the picker to a new booking sheet: who attends each service, so the phone can follow the service', function (): void {
    $owner = whoApp();
    $cut = $owner->business->services()->where('name', 'Corte')->value('id');
    $color = $owner->business->services()->where('name', 'Color')->value('id');
    $ana = $owner->business->professionals()->create(['name' => 'Ana Soto', 'sort_order' => 1]);
    $luis = $owner->business->professionals()->create(['name' => 'Luis Paz', 'sort_order' => 2]);
    $ana->services()->attach([$cut, $color]);
    $luis->services()->attach($cut);

    $data = asWhoApp($owner)->getJson('/api/v1/agenda/sheet?day='.whoMonday($owner)->format('Y-m-d'))->assertOk()->json('data');

    expect($data['copy']['form']['professional'])->toBe(__('agenda.form.professional'))
        ->and($data['copy']['form']['professionalPlaceholder'])->toBe(__('agenda.form.professional_placeholder'))
        ->and($data['sheet']['professionals'])->toBe([
            ['id' => $ana->id, 'name' => 'Ana Soto', 'services' => [$cut, $color]],
            ['id' => $luis->id, 'name' => 'Luis Paz', 'services' => [$cut]],
        ])
        ->and($data['sheet']['values']['professional_id'])->toBeNull()
        ->and($data['sheet']['professional'])->toBeNull();
});

it('sends no picker while nobody is set up', function (): void {
    $owner = whoApp();

    $sheet = asWhoApp($owner)->getJson('/api/v1/agenda/sheet?day='.whoMonday($owner)->format('Y-m-d'))->json('data.sheet');

    expect($sheet['professionals'])->toBe([]);
});

it('says who has a booking being moved, and offers only their hours', function (): void {
    $owner = whoApp();
    $ana = $owner->business->professionals()->create(['name' => 'Ana Soto']);
    ProfessionalHour::factory()->create(['business_id' => $owner->business_id, 'professional_id' => $ana->id, 'day_of_week' => 1, 'opens_at' => '09:00', 'closes_at' => '10:00']);
    $monday = whoMonday($owner);
    $booking = Appointment::factory()->create([
        'business_id' => $owner->business_id,
        'customer_id' => $owner->business->customers()->sole()->id,
        'professional_id' => $ana->id,
        'starts_at' => $monday->setTime(9, 0)->utc(),
        'ends_at' => $monday->setTime(9, 30)->utc(),
    ]);

    $sheet = asWhoApp($owner)->getJson('/api/v1/agenda/sheet?day='.$monday->format('Y-m-d').'&booking='.$booking->id)->assertOk()->json('data.sheet');

    expect($sheet['professional'])->toBe('con Ana Soto')
        ->and($sheet['professionals'])->toBe([])
        ->and($sheet['values']['professional_id'])->toBe($ana->id)
        ->and($sheet['hours'])->toBe(['09:00', '09:30']);
});

it('answers the hours of the professional asked for', function (): void {
    $owner = whoApp();
    $ana = $owner->business->professionals()->create(['name' => 'Ana Soto']);
    $owner->business->professionals()->create(['name' => 'Luis Paz']);
    ProfessionalHour::factory()->create(['business_id' => $owner->business_id, 'professional_id' => $ana->id, 'day_of_week' => 1, 'opens_at' => '09:00', 'closes_at' => '10:00']);
    $day = whoMonday($owner)->format('Y-m-d');

    $own = asWhoApp($owner)->getJson("/api/v1/agenda/hours?day={$day}&professional={$ana->id}")->assertOk()->json('data.hours');
    $any = asWhoApp($owner)->getJson("/api/v1/agenda/hours?day={$day}")->assertOk()->json('data.hours');

    expect($own)->toBe(['09:00', '09:30'])
        ->and($any)->toHaveCount(6);
});

it('books with the professional chosen, or lets the first free one take it', function (): void {
    $owner = whoApp();
    $ana = $owner->business->professionals()->create(['name' => 'Ana Soto', 'sort_order' => 1]);
    $luis = $owner->business->professionals()->create(['name' => 'Luis Paz', 'sort_order' => 2]);
    $customer = $owner->business->customers()->sole()->id;
    $day = whoMonday($owner)->format('Y-m-d');

    asWhoApp($owner)->postJson('/api/v1/agenda/bookings', ['customer_id' => $customer, 'professional_id' => $luis->id, 'day' => $day, 'time' => '09:00'])->assertCreated();
    asWhoApp($owner)->postJson('/api/v1/agenda/bookings', ['customer_id' => $customer, 'day' => $day, 'time' => '09:00'])->assertCreated();

    expect(Appointment::query()->orderBy('id')->pluck('professional_id')->all())->toBe([$luis->id, $ana->id]);
});

it('refuses a professional who does not attend the service, is switched off, or is of another business', function (): void {
    $owner = whoApp();
    $cut = $owner->business->services()->where('name', 'Corte')->value('id');
    $color = $owner->business->services()->where('name', 'Color')->value('id');
    $ana = $owner->business->professionals()->create(['name' => 'Ana Soto']);
    $ana->services()->attach($cut);
    $off = $owner->business->professionals()->create(['name' => 'Luis Paz', 'is_active' => false]);
    $stranger = Professional::factory()->create();
    $body = ['customer_id' => $owner->business->customers()->sole()->id, 'day' => whoMonday($owner)->format('Y-m-d'), 'time' => '09:00'];

    asWhoApp($owner)->postJson('/api/v1/agenda/bookings', [...$body, 'service_id' => $color, 'professional_id' => $ana->id])
        ->assertStatus(422)->assertJsonValidationErrors(['professional_id']);
    asWhoApp($owner)->postJson('/api/v1/agenda/bookings', [...$body, 'professional_id' => $off->id])
        ->assertStatus(422)->assertJsonValidationErrors(['professional_id']);
    asWhoApp($owner)->postJson('/api/v1/agenda/bookings', [...$body, 'professional_id' => $stranger->id])
        ->assertStatus(422)->assertJsonValidationErrors(['professional_id']);

    expect(Appointment::count())->toBe(0);
});

it('says with whom each booking of the day is, and null for one from before', function (): void {
    $owner = whoApp();
    $ana = $owner->business->professionals()->create(['name' => 'Ana Soto']);
    $monday = whoMonday($owner);
    $customer = $owner->business->customers()->sole()->id;
    Appointment::factory()->create(['business_id' => $owner->business_id, 'customer_id' => $customer, 'professional_id' => $ana->id, 'starts_at' => $monday->setTime(9, 0)->utc(), 'ends_at' => $monday->setTime(9, 30)->utc()]);
    Appointment::factory()->create(['business_id' => $owner->business_id, 'customer_id' => $customer, 'starts_at' => $monday->setTime(10, 0)->utc(), 'ends_at' => $monday->setTime(10, 30)->utc()]);

    $rows = asWhoApp($owner)->getJson('/api/v1/agenda?day='.$monday->format('Y-m-d'))->assertOk()->json('data.bookings');

    expect(collect($rows)->pluck('professional')->all())->toBe(['con Ana Soto', null]);
});

it('moves a booking keeping whoever has it', function (): void {
    $owner = whoApp();
    $ana = $owner->business->professionals()->create(['name' => 'Ana Soto']);
    $monday = whoMonday($owner);
    $booking = Appointment::factory()->create([
        'business_id' => $owner->business_id,
        'customer_id' => $owner->business->customers()->sole()->id,
        'professional_id' => $ana->id,
        'starts_at' => $monday->setTime(9, 0)->utc(),
        'ends_at' => $monday->setTime(9, 30)->utc(),
    ]);

    asWhoApp($owner)->putJson("/api/v1/agenda/bookings/{$booking->id}", ['day' => $monday->format('Y-m-d'), 'time' => '10:00'])->assertOk();

    expect($booking->fresh()->professional_id)->toBe($ana->id);
});
