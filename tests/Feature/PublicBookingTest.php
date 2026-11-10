<?php

declare(strict_types=1);

use App\Classes\Main\AddressBucket;
use App\Enums\AppointmentSource;
use App\Livewire\Forms\Agenda\PublicBookingForm;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\BusinessActivity;
use App\Models\BusinessSector;
use App\Models\Country;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Specialty;
use App\Models\User;
use App\Services\Tenant;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Features\SupportTesting\Testable;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

/** A business open 09:00-20:00 every day, with the agenda on. */
function bookableBusiness(array $attributes = []): Business
{
    $business = Business::factory()->create([
        'name' => 'Consultorio Vida',
        'timezone' => 'America/Argentina/Buenos_Aires',
        'appointments_enabled' => true,
        'appointment_slot_minutes' => 30,
        ...$attributes,
    ]);

    foreach (range(0, 6) as $weekday) {
        $business->hours()->create(['day_of_week' => $weekday, 'opens_at' => '09:00', 'closes_at' => '20:00']);
    }

    return $business;
}

function tomorrowAt(Business $business, string $time): array
{
    $day = CarbonImmutable::now($business->localTimezone())->addDay();

    return [$day->format('Y-m-d'), $time];
}

test('every business is born with a booking link of its own', function (): void {
    $business = bookableBusiness();
    $other = bookableBusiness();

    expect($business->booking_code)->not->toBeNull()
        ->and($business->booking_code)->not->toBe($other->booking_code)
        ->and($business->bookingLink())->toContain($business->booking_code);
});

test('a customer books from the public link without any account', function (): void {
    $business = bookableBusiness();
    [$day, $time] = tomorrowAt($business, '10:00');

    livewire('booking.public', ['code' => $business->booking_code])
        ->set('form.day', $day)
        ->call('pick', $time)
        ->set('form.name', 'Ana Gómez')
        ->set('form.phone', '+54 9 11 5555-0101')
        ->call('book')
        ->assertHasNoErrors()
        ->assertSee(__('agenda.public.done_title'));

    $booking = Appointment::withoutGlobalScopes()->sole();

    expect($booking->business_id)->toBe($business->id)
        ->and($booking->source)->toBe(AppointmentSource::Link)
        ->and($booking->starts_at->setTimezone($business->localTimezone())->format('Y-m-d H:i'))->toBe("{$day} {$time}")
        ->and(Customer::withoutGlobalScopes()->sole()->name)->toBe('Ana Gómez');
});

test('a second booking from the same phone reuses the customer', function (): void {
    $business = bookableBusiness();
    [$day] = tomorrowAt($business, '10:00');
    $existing = Customer::factory()->create(['business_id' => $business->id, 'phone' => '5491155550101', 'name' => 'Ana G.']);

    livewire('booking.public', ['code' => $business->booking_code])
        ->set('form.day', $day)
        ->call('pick', '11:00')
        ->set('form.name', 'Otra Ana')
        ->set('form.phone', '5491155550101')
        ->call('book')
        ->assertHasNoErrors();

    expect(Customer::withoutGlobalScopes()->count())->toBe(1)
        // A name already on file is the owner's: the link never overwrites it.
        ->and($existing->fresh()->name)->toBe('Ana G.')
        ->and(Appointment::withoutGlobalScopes()->sole()->customer_id)->toBe($existing->id);
});

test('the link refuses a booking without a name or a phone', function (): void {
    $business = bookableBusiness();
    [$day] = tomorrowAt($business, '10:00');

    livewire('booking.public', ['code' => $business->booking_code])
        ->set('form.day', $day)
        ->call('pick', '10:00')
        ->call('book')
        ->assertHasErrors(['name', 'phone']);

    expect(Appointment::withoutGlobalScopes()->count())->toBe(0);
});

test('an hour taken in the meantime is answered, never double booked', function (): void {
    $business = bookableBusiness();
    [$day, $time] = tomorrowAt($business, '10:00');
    $screen = livewire('booking.public', ['code' => $business->booking_code])
        ->set('form.day', $day)
        ->call('pick', $time)
        ->set('form.name', 'Ana Gómez')
        ->set('form.phone', '5491155550101');

    // Somebody else takes the hour between the page load and the tap.
    Appointment::factory()->create([
        'business_id' => $business->id,
        'customer_id' => Customer::factory()->create(['business_id' => $business->id])->id,
        'starts_at' => CarbonImmutable::parse("{$day} {$time}", $business->localTimezone())->utc(),
        'ends_at' => CarbonImmutable::parse("{$day} {$time}", $business->localTimezone())->addMinutes(30)->utc(),
    ]);

    $screen->call('book')->assertSee(__('agenda.public.taken'));

    expect(Appointment::withoutGlobalScopes()->count())->toBe(1);
});

test('the link is dead for a business without the agenda or suspended', function (): void {
    $off = bookableBusiness(['appointments_enabled' => false]);
    $suspended = bookableBusiness(['suspended_at' => now()]);

    $this->get(route('booking.public', ['code' => $off->booking_code]))->assertNotFound();
    $this->get(route('booking.public', ['code' => $suspended->booking_code]))->assertNotFound();
    $this->get(route('booking.public', ['code' => 'NOPE']))->assertNotFound();
});

test('the public page opens with no session at all', function (): void {
    $business = bookableBusiness();

    $this->get(route('booking.public', ['code' => $business->booking_code]))
        ->assertOk()
        ->assertSee($business->name)
        ->assertSee(__('agenda.public.title'));
});

test('a service is booked from the link even with somebody signed in who has no business yet', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $business = bookableBusiness();
    $service = Service::factory()->create(['business_id' => $business->id, 'is_bookable' => true, 'is_active' => true]);
    [$day, $time] = tomorrowAt($business, '10:00');

    // Somebody who registered and left the onboarding half way: their requests close the database fence ("0").
    $client = User::factory()->create(['business_id' => null]);
    $client->assignRole('client');
    $this->actingAs($client->refresh())->get(route('dashboard'));

    livewire('booking.public', ['code' => $business->booking_code])
        ->set('form.service_id', $service->id)
        ->set('form.day', $day)
        ->call('pick', $time)
        ->set('form.name', 'Ana Gómez')
        ->set('form.phone', '+54 9 11 5555-0101')
        ->call('book')
        ->assertHasNoErrors();

    // Read from inside the business: this test's own connection still carries the client's closed fence.
    expect(app(Tenant::class)->for($business->id, fn (): ?int => Appointment::query()->sole()->service_id))->toBe($service->id);
});

/** One tap of "Reservar mi turno" at the given hour, from a fresh page. */
function bookFromLink(Business $business, string $time, string $phone): Testable
{
    [$day] = tomorrowAt($business, $time);

    return livewire('booking.public', ['code' => $business->booking_code])
        ->set('form.day', $day)
        ->call('pick', $time)
        ->set('form.name', 'Ana Gómez')
        ->set('form.phone', $phone)
        ->call('book');
}

test('one WhatsApp number cannot fill a business agenda through the link', function (): void {
    $business = bookableBusiness();

    foreach (['10:00', '10:30', '11:00'] as $time) {
        bookFromLink($business, $time, '+54 9 11 5555-0101')->assertHasNoErrors();
    }

    // The number's own reason, not "from here": a mother booking for her children reads why.
    bookFromLink($business, '11:30', '+54 9 11 5555-0101')->assertSet('warning', __('agenda.public.too_many_phone'));

    expect(Appointment::withoutGlobalScopes()->count())->toBe(PublicBookingForm::PER_PHONE[0]);
});

test('one address cannot fill a business agenda with different numbers', function (): void {
    $business = bookableBusiness();

    foreach (['10:00', '10:30', '11:00', '11:30', '12:00'] as $index => $time) {
        bookFromLink($business, $time, '+54 9 11 5555-010'.$index)->assertHasNoErrors();
    }

    bookFromLink($business, '12:30', '+54 9 11 5555-0109')->assertSet('warning', __('agenda.public.too_many'));

    expect(Appointment::withoutGlobalScopes()->count())->toBe(PublicBookingForm::PER_ADDRESS[0]);
});

test('rotating addresses inside one IPv6 /64 does not reset the allowance', function (): void {
    $business = bookableBusiness();

    // One home line hands out a whole /64: each booking from a different address inside it.
    foreach (['10:00', '10:30', '11:00', '11:30', '12:00'] as $index => $time) {
        request()->server->set('REMOTE_ADDR', '2800:a4:1234:5678::'.dechex($index + 1));
        bookFromLink($business, $time, '+54 9 11 5555-010'.$index)->assertHasNoErrors();
    }

    request()->server->set('REMOTE_ADDR', '2800:a4:1234:5678:ffff:ffff:ffff:ffff');
    bookFromLink($business, '12:30', '+54 9 11 5555-0109')->assertSet('warning', __('agenda.public.too_many'));

    expect(Appointment::withoutGlobalScopes()->count())->toBe(PublicBookingForm::PER_ADDRESS[0]);
});

test('an address is counted by its /64 on IPv6 and as is on IPv4', function (string $ip, string $bucket): void {
    expect(AddressBucket::of($ip))->toBe($bucket);
})->with([
    'IPv6' => ['2800:a4:1234:5678:9abc::1', '2800:a4:1234:5678::/64'],
    'IPv6, same /64' => ['2800:a4:1234:5678:ffff:1:2:3', '2800:a4:1234:5678::/64'],
    'IPv4' => ['190.12.34.56', '190.12.34.56'],
    'IPv4 written as IPv6' => ['::ffff:190.12.34.56', '190.12.34.56'],
    'nothing' => ['', ''],
]);

test('every numeric throttle counts an IPv6 visitor by its /64, not by the exact address', function (): void {
    $business = bookableBusiness();
    $page = route('booking.public', $business->booking_code);

    foreach (range(1, 20) as $index) {
        $this->withServerVariables(['REMOTE_ADDR' => '2800:a4:1234:5678::'.dechex($index)])->get($page)->assertOk();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '2800:a4:1234:5678::ffff'])->get($page)->assertStatus(429);
    $this->withServerVariables(['REMOTE_ADDR' => '2800:a4:1234:9999::1'])->get($page)->assertOk();
});

test('a typed number becomes the one WhatsApp sends, however the visitor wrote it', function (string $code, string $typed, string $stored): void {
    $business = bookableBusiness(['country_id' => Country::factory()->create(['phone_code' => $code])->id]);

    expect($business->internationalPhone($typed))->toBe($stored);
})->with([
    'Venezuela, with the trunk 0' => ['+58', '0412-555 1234', '584125551234'],
    'Venezuela, without the 0' => ['+58', '412 5551234', '584125551234'],
    'Venezuela, already international' => ['+58', '584125551234', '584125551234'],
    'Venezuela, with + and the 0' => ['+58', '+58 0412 5551234', '584125551234'],
    'Venezuela, with 00' => ['+58', '0058 412 5551234', '584125551234'],
    'Argentina, a mobile the local way' => ['+54', '11 5555 0101', '5491155550101'],
    'Argentina, with + as WhatsApp shows it' => ['+54', '+54 9 11 5555-0101', '5491155550101'],
    'Mexico' => ['+52', '55 1234 5678', '525512345678'],
    'Colombia' => ['+57', '300 123 4567', '573001234567'],
    'Guatemala, a national number that looks like another code' => ['+502', '5803 1234', '50258031234'],
]);

test('a customer saved with the typed number before is found again, not duplicated', function (): void {
    $business = bookableBusiness(['country_id' => Country::factory()->create(['phone_code' => '+58'])->id]);
    $old = Customer::factory()->create(['business_id' => $business->id, 'phone' => '04125551234']);

    expect($business->customerWithPhone('04125551234', 'Ana')->id)->toBe($old->id)
        ->and(Customer::withoutGlobalScopes()->where('business_id', $business->id)->count())->toBe(1);
});

test('an hour after midnight of a night shift is offered and really booked', function (): void {
    $business = bookableBusiness(['appointment_slot_minutes' => 60]);
    $business->hours()->delete();
    $friday = CarbonImmutable::now($business->localTimezone())->next(CarbonImmutable::FRIDAY);
    $business->hours()->create(['day_of_week' => 5, 'opens_at' => '22:00', 'closes_at' => '02:00']);

    // Picked from Friday's list: "01:00" there is Saturday 01:00.
    livewire('booking.public', ['code' => $business->booking_code])
        ->set('form.day', $friday->format('Y-m-d'))
        ->call('pick', '01:00')
        ->set('form.name', 'Noche')
        ->set('form.phone', '+54 9 11 5555-0199')
        ->call('book')
        ->assertHasNoErrors();

    expect(Appointment::withoutGlobalScopes()->sole()->starts_at->setTimezone($business->localTimezone())->format('D H:i'))->toBe('Sat 01:00');
});

test('a paused service is not booked through a page opened before the pause', function (): void {
    $business = bookableBusiness();
    $service = app(Tenant::class)->for($business->id, fn (): Service => Service::factory()->create(['business_id' => $business->id, 'is_bookable' => true, 'is_active' => true]));
    [$day] = tomorrowAt($business, '10:00');

    $page = livewire('booking.public', ['code' => $business->booking_code])
        ->set('form.service_id', $service->id)
        ->set('form.day', $day)
        ->call('pick', '10:00')
        ->set('form.name', 'Ana Gómez')
        ->set('form.phone', '+54 9 11 5555-0301');

    app(Tenant::class)->for($business->id, fn () => $service->update(['is_active' => false]));

    $page->call('book');

    expect(Appointment::withoutGlobalScopes()->count())->toBe(0);
});

test('the confirmation offers to turn on the reminder from the person own WhatsApp', function (): void {
    $business = bookableBusiness(['whatsapp_instance' => 'negocio-9', 'whatsapp_connected_at' => now()]);
    Cache::put('wa:profile:'.$business->id, ['number' => '584121234567', 'name' => 'Consultorio Vida', 'picture' => null], 600);

    $page = bookFromLink($business, '10:00', '+54 9 11 5555-0401')
        ->assertSee(__('agenda.public.reminder_button'));

    expect($page->get('reminderLink'))->toStartWith('https://wa.me/584121234567?text=')
        ->and(rawurldecode((string) $page->get('reminderLink')))->toContain('Consultorio Vida');
});

test('the confirmation offers to save the booked hour in Google Calendar, in UTC', function (): void {
    $business = bookableBusiness();
    $page = bookFromLink($business, '10:00', '+54 9 11 5555-0451')
        ->assertSee(__('agenda.public.calendar_button'));

    $booking = Appointment::withoutGlobalScopes()->sole();
    $link = (string) $page->get('calendarLink');
    $utc = fn (CarbonImmutable $at): string => $at->utc()->format('Ymd\THis\Z');

    expect($link)->toStartWith('https://calendar.google.com/calendar/render?action=TEMPLATE')
        ->and(rawurldecode($link))->toContain('Turno en Consultorio Vida')
        ->and(rawurldecode($link))->toContain('dates='.$utc($booking->starts_at).'/'.$utc($booking->ends_at));
});

test('the calendar event points at the business door, and at none when it has no premises', function (): void {
    $withDoor = bookFromLink(bookableBusiness(['address' => 'Av. Libertador 1234', 'city' => 'Caracas', 'has_premises' => true]), '10:00', '+54 9 11 5555-0461');
    $withoutDoor = bookFromLink(bookableBusiness(['address' => 'Av. Libertador 1234', 'city' => 'Caracas', 'has_premises' => false]), '10:00', '+54 9 11 5555-0471');

    expect(rawurldecode((string) $withDoor->get('calendarLink')))->toContain('location=Av. Libertador 1234, Caracas')
        ->and((string) $withoutDoor->get('calendarLink'))->not->toContain('location=');
});

test('the confirmation hands out the booked hour as an .ics file for other calendars', function (): void {
    $business = bookableBusiness(['address' => 'Av. Libertador 1234', 'city' => 'Caracas', 'has_premises' => true]);
    $page = bookFromLink($business, '10:00', '+54 9 11 5555-0481')
        ->assertSee(__('agenda.public.calendar_file_button'));

    $booking = Appointment::withoutGlobalScopes()->sole();
    $response = $this->get((string) $page->get('calendarFileLink'));
    $file = $response->getContent();

    $response->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
    expect($file)->toStartWith("BEGIN:VCALENDAR\r\n")
        ->and($file)->toContain('SUMMARY:Turno en Consultorio Vida')
        ->and($file)->toContain('DTSTART:'.$booking->starts_at->utc()->format('Ymd\THis\Z'))
        ->and($file)->toContain('DTEND:'.$booking->ends_at->utc()->format('Ymd\THis\Z'))
        ->and($file)->toContain('LOCATION:Av. Libertador 1234\, Caracas');
});

test('the .ics link cannot be walked to another booking by changing its number', function (): void {
    $business = bookableBusiness();
    $page = bookFromLink($business, '10:00', '+54 9 11 5555-0491');
    $other = bookFromLink($business, '11:00', '+54 9 11 5555-0492');
    $otherId = Appointment::withoutGlobalScopes()->latest('id')->value('id');

    $tampered = preg_replace('#/turno/\d+\.ics#', '/turno/'.$otherId.'.ics', (string) $page->get('calendarFileLink'));

    $this->get($tampered)->assertForbidden();
    $this->get((string) $other->get('calendarFileLink'))->assertOk();
});

test('without a linked line the confirmation promises no reminder', function (): void {
    bookFromLink(bookableBusiness(), '10:00', '+54 9 11 5555-0501')
        ->assertSee(__('agenda.public.done_title'))
        ->assertDontSee(__('agenda.public.reminder_button'));
});

test('an hour that was taken does not use up the allowance', function (): void {
    $business = bookableBusiness();
    bookFromLink($business, '10:00', '+54 9 11 5555-0201');

    foreach (range(1, 6) as $try) {
        bookFromLink($business, '10:00', '+54 9 11 5555-0202');
    }

    bookFromLink($business, '10:30', '+54 9 11 5555-0202')->assertHasNoErrors();

    expect(Appointment::withoutGlobalScopes()->count())->toBe(2);
});

test('the public link tells the customer which specialties the business marked, and says nothing when it marked none', function (): void {
    $sector = BusinessSector::factory()->create();
    $office = BusinessActivity::factory()->create(['business_sector_id' => $sector->id, 'specialty_label' => 'Especialidad']);
    Specialty::factory()->for($office, 'activity')->create(['code' => 'neurologia', 'name' => 'Neurología']);

    $business = bookableBusiness();
    $plain = bookableBusiness(['name' => 'Otro consultorio']);

    $business->syncActivities($office->id);
    $business->syncSpecialties(['neurologia']);

    livewire('booking.public', ['code' => $business->booking_code])
        ->assertSeeHtml('data-testid="booking-specialties"')
        ->assertSee('Especialidad: Neurología');

    livewire('booking.public', ['code' => $plain->booking_code])
        ->assertDontSeeHtml('data-testid="booking-specialties"');
});
