<?php

declare(strict_types=1);

use App\Actions\Business\SyncProfileKnowledge;
use App\Ai\Tools\CheckBusinessHours;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\BusinessClosure;
use App\Models\CountryHoliday;
use App\Models\Customer;
use App\Models\KnowledgeDocument;
use App\Models\User;
use App\Services\Agenda\SlotFinder;
use App\Services\EvolutionApi;
use Carbon\CarbonImmutable;
use Database\Seeders\CountryHolidaySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Tools\Request;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The days the business does not open
|--------------------------------------------------------------------------
| A holiday falling on a Tuesday used to be answered as a normal Tuesday:
| open, and offering hours. Everything funnels through two answers — the
| clock and the free slots — so a closure that holds in both holds for the
| agenda, the four booking skills and the public booking link alike.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    Queue::fake();
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->business = Business::factory()->create([
        'timezone' => 'America/Argentina/Buenos_Aires',
        'appointments_enabled' => true,
        'appointment_slot_minutes' => 60,
    ]);

    foreach (range(0, 6) as $weekday) {
        $this->business->hours()->create(['day_of_week' => $weekday, 'opens_at' => '09:00', 'closes_at' => '18:00']);
    }

    $this->owner = User::factory()->create();
    $this->owner->business()->associate($this->business)->save();
});

afterEach(function (): void {
    Date::setTestNow();
});

test('the clock says closed on a day she marked closed, open hours and all', function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-10-13 11:00', $this->business->localTimezone()));

    expect($this->business->isOpenNow())->toBeTrue();

    $this->business->closures()->create(['starts_on' => '2026-10-13', 'ends_on' => '2026-10-13']);

    expect($this->business->fresh()->isOpenNow())->toBeFalse();
});

test('a closed day offers no hours at all', function (): void {
    $day = CarbonImmutable::parse('2026-10-13 00:00', $this->business->localTimezone());
    $finder = app(SlotFinder::class);

    expect($finder->freeSlots($this->business, $day, null, $day->setTime(8, 0)))->not->toBeEmpty();

    $this->business->closures()->create(['starts_on' => '2026-10-13', 'ends_on' => '2026-10-13']);

    expect($finder->freeSlots($this->business->fresh(), $day, null, $day->setTime(8, 0)))->toBe([]);
});

test('a range covers every day inside it, and nothing outside', function (): void {
    $this->business->closures()->create(['starts_on' => '2026-12-24', 'ends_on' => '2027-01-02']);
    $business = $this->business->fresh();

    expect($business->isClosedOn(CarbonImmutable::parse('2026-12-24')))->toBeTrue()
        ->and($business->isClosedOn(CarbonImmutable::parse('2026-12-28')))->toBeTrue()
        ->and($business->isClosedOn(CarbonImmutable::parse('2027-01-02')))->toBeTrue()
        ->and($business->isClosedOn(CarbonImmutable::parse('2026-12-23')))->toBeFalse()
        ->and($business->isClosedOn(CarbonImmutable::parse('2027-01-03')))->toBeFalse();
});

test('the assistant says the day is closed instead of its usual hours', function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-10-05 10:00', $this->business->localTimezone()));
    $this->business->closures()->create([
        'starts_on' => '2026-10-13',
        'ends_on' => '2026-10-13',
        'reason' => 'Feriado nacional',
    ]);

    $answer = (string) (new CheckBusinessHours($this->business->fresh()))
        ->handle(new Request(['date' => '2026-10-13']));

    expect($answer)->toContain('no abre ese día')
        ->toContain('Feriado nacional')
        ->not->toContain('Ningún cierre especial');
});

test('the assistant lists what is still ahead when asked about hours', function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-10-05 10:00', $this->business->localTimezone()));
    $this->business->closures()->create(['starts_on' => '2026-12-24', 'ends_on' => '2027-01-02', 'reason' => 'Vacaciones']);

    $answer = (string) (new CheckBusinessHours($this->business->fresh()))->handle(new Request([]));

    expect($answer)->toContain('Días distintos')
        ->toContain('24/12/2026 – 02/01/2027')
        ->toContain('Vacaciones');
});

test('the knowledge document carries the closures with the week', function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-10-05 10:00', $this->business->localTimezone()));
    $this->business->closures()->create(['starts_on' => '2026-10-13', 'ends_on' => '2026-10-13', 'reason' => 'Feriado']);

    app(SyncProfileKnowledge::class)->handle($this->business->fresh());

    $document = KnowledgeDocument::withoutGlobalScopes()
        ->where('business_id', $this->business->id)
        ->where('source_type', 'profile')
        ->sole();

    expect($document->content)->toContain('Días distintos: 13/10/2026: no abre (Feriado)');
});

test('she loads a closure from the hours card and can take it back out', function (): void {
    Livewire::actingAs($this->owner)
        ->test('client.section-hours')
        ->set('closures.range', '2026-12-24..2027-01-02')
        ->set('closures.reason', 'Vacaciones')
        ->call('addClosure')
        ->assertHasNoErrors();

    $closure = $this->business->closures()->sole();

    expect($closure->label())->toBe('24/12/2026 – 02/01/2027')
        ->and($closure->reason)->toBe('Vacaciones');

    Livewire::actingAs($this->owner)
        ->test('client.section-hours')
        ->call('removeClosure', $closure->id);

    expect($this->business->closures()->count())->toBe(0);
});

test('a single day is stored as a range of one', function (): void {
    Livewire::actingAs($this->owner)
        ->test('client.section-hours')
        ->set('closures.range', '2026-10-13')
        ->call('addClosure')
        ->assertHasNoErrors();

    $closure = $this->business->closures()->sole();

    expect($closure->starts_on->isSameDay($closure->ends_on))->toBeTrue()
        ->and($closure->label())->toBe('13/10/2026');
});

test('a closure that steps on one already loaded is refused', function (): void {
    $this->business->closures()->create(['starts_on' => '2026-12-24', 'ends_on' => '2027-01-02']);

    Livewire::actingAs($this->owner)
        ->test('client.section-hours')
        ->set('closures.range', '2026-12-28..2026-12-30')
        ->call('addClosure');

    expect($this->business->closures()->count())->toBe(1);
});

test('a date that is not a real date never reaches the table', function (): void {
    Livewire::actingAs($this->owner)
        ->test('client.section-hours')
        ->set('closures.range', 'el 24 de diciembre')
        ->call('addClosure')
        ->assertHasErrors('range');

    expect($this->business->closures()->count())->toBe(0);
});

test('a day with special hours stays open, on those hours only', function (): void {
    $this->business->closures()->create([
        'starts_on' => '2026-12-24',
        'ends_on' => '2026-12-24',
        'opens_at' => '09:00',
        'closes_at' => '13:00',
        'reason' => 'Nochebuena',
    ]);
    $business = $this->business->fresh();

    // Inside the short hours, open; at 15:00 it would be open on a normal day.
    Date::setTestNow(CarbonImmutable::parse('2026-12-24 11:00', $business->localTimezone()));
    expect($business->isOpenNow())->toBeTrue()
        ->and($business->isClosedOn(CarbonImmutable::parse('2026-12-24')))->toBeFalse();

    Date::setTestNow(CarbonImmutable::parse('2026-12-24 15:00', $business->localTimezone()));
    expect($business->fresh()->isOpenNow())->toBeFalse();
});

test('the agenda offers the special hours and not the weekly ones', function (): void {
    $this->business->closures()->create([
        'starts_on' => '2026-12-24',
        'ends_on' => '2026-12-24',
        'opens_at' => '09:00',
        'closes_at' => '11:00',
    ]);

    $day = CarbonImmutable::parse('2026-12-24 00:00', $this->business->localTimezone());
    $slots = app(SlotFinder::class)->freeSlots($this->business->fresh(), $day, null, $day->setTime(8, 0));

    expect(array_map(fn (CarbonImmutable $slot): string => $slot->format('H:i'), $slots))
        ->toBe(['09:00', '10:00']);
});

test('the assistant says the short hours instead of the usual ones', function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-12-01 10:00', $this->business->localTimezone()));
    $this->business->closures()->create([
        'starts_on' => '2026-12-24',
        'ends_on' => '2026-12-24',
        'opens_at' => '09:00',
        'closes_at' => '13:00',
        'reason' => 'Nochebuena',
    ]);

    $answer = (string) (new CheckBusinessHours($this->business->fresh()))
        ->handle(new Request(['date' => '2026-12-24']));

    expect($answer)->toContain('abre solo de 09:00 – 13:00')
        ->toContain('Nochebuena')
        ->not->toContain('no abre ese día');
});

test('one lone time is refused: hours are a pair', function (): void {
    Livewire::actingAs($this->owner)
        ->test('client.section-hours')
        ->set('closures.range', '2026-12-24')
        ->set('closures.opens_at', '09:00')
        ->call('addClosure')
        ->assertHasErrors('closes_at');

    expect($this->business->closures()->count())->toBe(0);
});

test('the national holidays of her country load in one click, and twice adds nothing', function (): void {
    // The business already has a country of its own: naming it ARG is what
    // the holiday seeder keys on, and seeding countries here would collide
    // with the one the factory just made.
    $this->business->country->forceFill(['code' => 'ARG'])->save();
    $this->seed(CountryHolidaySeeder::class);

    Livewire::actingAs($this->owner)
        ->test('client.section-hours')
        ->call('importHolidays');

    $loaded = $this->business->closures()->count();

    expect($loaded)->toBeGreaterThan(0)
        ->and($this->business->closures()->pluck('reason'))->toContain('Navidad');

    Livewire::actingAs($this->owner)
        ->test('client.section-hours')
        ->call('importHolidays');

    expect($this->business->closures()->count())->toBe($loaded);
});

test('a holiday she already loaded by hand is never overwritten', function (): void {
    // The business already has a country of its own: naming it ARG is what
    // the holiday seeder keys on, and seeding countries here would collide
    // with the one the factory just made.
    $this->business->country->forceFill(['code' => 'ARG'])->save();
    $this->seed(CountryHolidaySeeder::class);

    $christmas = CarbonImmutable::today()->addYear()->setDate((int) now()->addYear()->format('Y'), 12, 25);
    $this->business->closures()->create([
        'starts_on' => $christmas->format('Y-m-d'),
        'ends_on' => $christmas->format('Y-m-d'),
        'reason' => 'Cerramos toda la semana',
    ]);

    Livewire::actingAs($this->owner)
        ->test('client.section-hours')
        ->call('importHolidays');

    expect($this->business->closures()->where('starts_on', $christmas->format('Y-m-d'))->pluck('reason'))
        ->toContain('Cerramos toda la semana');
});

test('Easter moves the holidays that hang off it', function (): void {
    // Easter 2026 is 5 April, so Good Friday is the 3rd; 2027 it is 28 March.
    expect(CountryHoliday::easter(2026)->format('Y-m-d'))->toBe('2026-04-05')
        ->and(CountryHoliday::easter(2027)->format('Y-m-d'))->toBe('2027-03-28');
});

test('the people holding an hour on a closed day are told, and the hour is freed', function (): void {
    $whatsapp = mock(EvolutionApi::class);
    $whatsapp->shouldReceive('sendText')->once()->andReturn('MSG-1');
    app()->instance(EvolutionApi::class, $whatsapp);

    $this->business->update(['whatsapp_instance' => 'business-1', 'whatsapp_connected_at' => now()]);
    $customer = Customer::factory()->create(['business_id' => $this->business->id, 'phone' => '5493415557788']);

    $closure = $this->business->closures()->create([
        'starts_on' => '2026-12-24',
        'ends_on' => '2026-12-24',
        'reason' => 'Nochebuena',
    ]);

    $appointment = Appointment::factory()->create([
        'business_id' => $this->business->id,
        'customer_id' => $customer->id,
        'starts_at' => CarbonImmutable::parse('2026-12-24 10:00', $this->business->localTimezone()),
        'ends_at' => CarbonImmutable::parse('2026-12-24 11:00', $this->business->localTimezone()),
        'status' => AppointmentStatus::Confirmed,
    ]);

    Livewire::actingAs($this->owner)
        ->test('client.section-hours')
        ->call('notifyClosure', $closure->id);

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Cancelled);
});

test('a suspended business tells nobody anything', function (): void {
    $whatsapp = mock(EvolutionApi::class);
    $whatsapp->shouldReceive('sendText')->never();
    app()->instance(EvolutionApi::class, $whatsapp);

    $this->business->forceFill(['suspended_at' => now()])->save();
    $customer = Customer::factory()->create(['business_id' => $this->business->id, 'phone' => '5493415557788']);

    $closure = $this->business->closures()->create(['starts_on' => '2026-12-24', 'ends_on' => '2026-12-24']);

    Appointment::factory()->create([
        'business_id' => $this->business->id,
        'customer_id' => $customer->id,
        'starts_at' => CarbonImmutable::parse('2026-12-24 10:00', $this->business->localTimezone()),
        'ends_at' => CarbonImmutable::parse('2026-12-24 11:00', $this->business->localTimezone()),
        'status' => AppointmentStatus::Confirmed,
    ]);

    Livewire::actingAs($this->owner)
        ->test('client.section-hours')
        ->call('notifyClosure', $closure->id);
});

test('another business cannot be reached by passing its closure id', function (): void {
    $other = Business::factory()->create();
    $theirs = BusinessClosure::factory()->create(['business_id' => $other->id]);

    Livewire::actingAs($this->owner)
        ->test('client.section-hours')
        ->call('removeClosure', $theirs->id);

    expect(BusinessClosure::withoutGlobalScopes()->find($theirs->id))->not->toBeNull();
});
