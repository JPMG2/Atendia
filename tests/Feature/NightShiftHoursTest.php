<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\User;
use App\Services\Agenda\SlotFinder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Opening hours that step on each other, and the ones that cross midnight
|--------------------------------------------------------------------------
| Two shifts that overlap offer the SAME hour twice, to the owner and to her
| customer over WhatsApp. And a bar that closes at 02:00 could not be loaded
| at all. Both are the same rule: what a shift's two times mean.
*/

/** The owner of a business, on her "Mi negocio" hours card. */
function hoursOwner(array $attributes = []): User
{
    $user = User::factory()->create();
    $user->business()->associate(Business::factory()->create([
        'timezone' => 'America/Argentina/Buenos_Aires',
        ...$attributes,
    ]))->save();

    return $user;
}

/** @param list<array{string, string}> $shifts Monday's shifts. */
function saveMonday(User $user, array $shifts): Testable
{
    $week = array_fill_keys([1, 2, 3, 4, 5, 6, 0], []);
    $week[1] = array_map(fn (array $pair): array => ['opens_at' => $pair[0], 'closes_at' => $pair[1]], $shifts);

    return Livewire::actingAs($user)
        ->test('client.section-hours')
        ->set('form.week', $week)
        ->call('save');
}

test('two shifts that step on each other are refused, naming the one to move', function (): void {
    $user = hoursOwner();

    saveMonday($user, [['09:00', '18:00'], ['14:00', '20:00']])
        ->assertHasErrors('week.1.1.opens_at');

    expect($user->business->hours()->count())->toBe(0);
});

test('shifts that only touch are fine: a split day is not an overlap', function (): void {
    $user = hoursOwner();

    saveMonday($user, [['09:00', '12:00'], ['12:00', '18:00']])->assertHasNoErrors();

    expect($user->business->hours()->count())->toBe(2);
});

test('a shift that closes after midnight can finally be loaded', function (): void {
    $user = hoursOwner();

    saveMonday($user, [['22:00', '02:00']])->assertHasNoErrors();

    $shift = $user->business->hours()->sole();

    expect($shift->runsPastMidnight())->toBeTrue()
        ->and($shift->spanInMinutes())->toBe([1320, 1560]);
});

test('a night shift still cannot be stepped on by the hours it runs into', function (): void {
    $user = hoursOwner();

    // 22:00-02:00 occupies 00:00-02:00 of the next day, so 01:00-03:00 collides.
    saveMonday($user, [['22:00', '02:00'], ['01:00', '03:00']])
        ->assertHasErrors('week.1.1.opens_at');
});

test('a shift that opens and closes at the same time means nothing', function (): void {
    $user = hoursOwner();

    saveMonday($user, [['09:00', '09:00']])->assertHasErrors('week.1.0.closes_at');
});

test('the clock says open during the small hours of a night shift', function (): void {
    $user = hoursOwner();
    $business = $user->business;
    // Saturday 22:00 to Sunday 02:00.
    $business->hours()->create(['day_of_week' => 6, 'opens_at' => '22:00', 'closes_at' => '02:00']);

    // Sunday at 01:00 local: what is open is SATURDAY's shift.
    Date::setTestNow(CarbonImmutable::parse('2026-10-04 01:00', $business->localTimezone()));
    expect($business->fresh()->isOpenNow())->toBeTrue();

    // Sunday at 03:00: the night is over and Sunday has no shift of its own.
    Date::setTestNow(CarbonImmutable::parse('2026-10-04 03:00', $business->localTimezone()));
    expect($business->fresh()->isOpenNow())->toBeFalse();

    Date::setTestNow();
});

test('the agenda offers the hours after midnight as slots of the night shift', function (): void {
    $user = hoursOwner([
        'appointments_enabled' => true,
        'appointment_slot_minutes' => 60,
    ]);
    $business = $user->business;
    // Friday 22:00 to Saturday 01:00.
    $business->hours()->create(['day_of_week' => 5, 'opens_at' => '22:00', 'closes_at' => '01:00']);

    $friday = CarbonImmutable::parse('2026-10-09 00:00', $business->localTimezone());
    $slots = app(SlotFinder::class)->freeSlots(
        $business->fresh(),
        $friday,
        null,
        $friday->setTime(8, 0),
    );

    expect(array_map(fn (CarbonImmutable $slot): string => $slot->format('d/m H:i'), $slots))
        ->toBe(['09/10 22:00', '09/10 23:00', '10/10 00:00']);
});
