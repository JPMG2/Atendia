<?php

declare(strict_types=1);

use App\Classes\Main\Client;
use App\Dto\ProfessionalDto;
use App\Livewire\Forms\Client\ProfessionalForm;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\Professional;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

/** A business with two services that take a booking and one that does not, and its owner signed in. */
function proPieceBusiness(): array
{
    $business = Business::factory()->create();
    $cut = $business->services()->create(['name' => 'Corte', 'duration_minutes' => 30, 'is_bookable' => true]);
    $color = $business->services()->create(['name' => 'Color', 'duration_minutes' => 90, 'is_bookable' => true]);
    $shampoo = $business->services()->create(['name' => 'Shampoo', 'is_bookable' => false]);

    $user = User::factory()->create(['business_id' => $business->id]);
    test()->actingAs($user);

    return [$business, Client::for($user->fresh())->professionals, $cut, $color, $shampoo];
}

/** @return array<string, mixed> The payload a sheet validates and the action stores. */
function proPayload(array $overrides = []): array
{
    return ProfessionalDto::fromArray(['name' => 'Ana', ...$overrides])->toPayload();
}

test('a business with no professionals has the piece but nobody on it yet', function (): void {
    [, $piece] = proPieceBusiness();

    expect($piece->hasAny)->toBeFalse()
        ->and($piece->all)->toHaveCount(0)
        ->and($piece->bookableServices->pluck('name')->all())->toBe(['Color', 'Corte']);
});

test('a client with no business has no professionals piece', function (): void {
    $user = User::factory()->create(['business_id' => null]);

    expect(Client::for($user)->professionals)->toBeNull();
});

test('a new sheet lists every bookable service switched off, and follows the business hours', function (): void {
    [, $piece, $cut, $color, $shampoo] = proPieceBusiness();

    $sheet = $piece->sheetOf();

    expect(array_keys($sheet->services))->toBe([$color->id, $cut->id])
        ->and(array_keys($sheet->services))->not->toContain($shampoo->id)
        ->and(collect($sheet->services)->pluck('attends')->unique()->all())->toBe([false])
        ->and($sheet->uses_business_hours)->toBeTrue()
        ->and($sheet->week[1]['open'])->toBeTrue()
        ->and($sheet->week[0]['open'])->toBeFalse();
});

test('saving stores the person, their own week and what they change for each service', function (): void {
    [$business, $piece, $cut, $color] = proPieceBusiness();
    $week = [1 => ['open' => true, 'opens_at' => '09:00', 'closes_at' => '13:00'], 3 => ['open' => true, 'opens_at' => '14:00', 'closes_at' => '18:00']];

    $professional = $piece->save(proPayload([
        'uses_business_hours' => false,
        'week' => $week,
        'appointment_capacity' => '2',
        'appointments_per_day' => '8',
        'services' => [
            $cut->id => ['attends' => true, 'duration_minutes' => '20', 'price_type' => 'from', 'price' => '15', 'deposit' => '5'],
            $color->id => ['attends' => true, 'duration_minutes' => null, 'price_type' => null, 'price' => null, 'deposit' => null],
        ],
    ]));

    $stored = $professional->fresh();
    $pivot = $stored->services()->whereKey($cut->id)->first()->pivot;

    expect($stored->business_id)->toBe($business->id)
        ->and($stored->appointment_capacity)->toBe(2)
        ->and($stored->appointments_per_day)->toBe(8)
        ->and($stored->hours->map(fn ($shift): string => $shift->day_of_week.' '.substr((string) $shift->opens_at, 0, 5).'-'.substr((string) $shift->closes_at, 0, 5))->all())
        ->toBe(['1 09:00-13:00', '3 14:00-18:00'])
        ->and($stored->hours->pluck('business_id')->unique()->all())->toBe([$business->id])
        ->and($pivot->duration_minutes)->toBe(20)
        ->and($pivot->price_type)->toBe('from')
        ->and((float) $pivot->price)->toBe(15.0)
        ->and($stored->services()->whereKey($color->id)->first()->pivot->duration_minutes)->toBeNull();
});

test('following the business hours stores no shifts, and the sheet reads it back that way', function (): void {
    [, $piece] = proPieceBusiness();

    $professional = $piece->save(proPayload(['uses_business_hours' => true]));

    expect($professional->hours()->count())->toBe(0)
        ->and($piece->sheetOf($professional->id)->uses_business_hours)->toBeTrue();
});

test('editing replaces the week and the services, and the sheet round-trips what was saved', function (): void {
    [, $piece, $cut, $color] = proPieceBusiness();
    $first = $piece->save(proPayload([
        'uses_business_hours' => false,
        'week' => [1 => ['open' => true, 'opens_at' => '09:00', 'closes_at' => '13:00']],
        'services' => [$cut->id => ['attends' => true, 'duration_minutes' => '20', 'price_type' => 'fixed', 'price' => '15.50', 'deposit' => null]],
    ]));

    $piece->save(proPayload([
        'name' => 'Ana María',
        'uses_business_hours' => false,
        'week' => [2 => ['open' => true, 'opens_at' => '10:00', 'closes_at' => '12:00']],
        'services' => [$color->id => ['attends' => true, 'duration_minutes' => '60', 'price_type' => null, 'price' => null, 'deposit' => null]],
    ]), $first->id);

    $sheet = $piece->sheetOf($first->id);

    expect(Professional::count())->toBe(1)
        ->and($sheet->name)->toBe('Ana María')
        ->and($sheet->week[1]['open'])->toBeFalse()
        ->and($sheet->week[2])->toBe(['open' => true, 'opens_at' => '10:00', 'closes_at' => '12:00'])
        ->and($sheet->services[$cut->id]['attends'])->toBeFalse()
        ->and($sheet->services[$color->id]['attends'])->toBeTrue()
        ->and($sheet->services[$color->id]['duration_minutes'])->toBe('60');
});

test('a price kept with a decimal reads back the way it was typed', function (): void {
    [, $piece, $cut] = proPieceBusiness();
    $professional = $piece->save(proPayload(['services' => [$cut->id => ['attends' => true, 'duration_minutes' => null, 'price_type' => 'fixed', 'price' => '15000', 'deposit' => '2500']]]));

    $row = $piece->sheetOf($professional->id)->services[$cut->id];

    expect($row['price'])->toBe('15000')
        ->and($row['deposit'])->toBe('2500');
});

test('a price type that says free or to talk drops the amount', function (): void {
    expect(proPayload(['services' => [7 => ['attends' => true, 'duration_minutes' => null, 'price_type' => 'talk', 'price' => '99', 'deposit' => null]]])['services'][7]['price'])->toBeNull();
});

test('a service that takes no booking, or belongs to another business, never reaches the professional', function (): void {
    [, $piece, $cut, , $shampoo] = proPieceBusiness();
    $foreign = Business::factory()->create()->services()->create(['name' => 'Ajeno', 'is_bookable' => true]);
    $none = ['duration_minutes' => null, 'price_type' => null, 'price' => null, 'deposit' => null];

    $professional = $piece->save(proPayload(['services' => [
        $cut->id => ['attends' => true, ...$none],
        $shampoo->id => ['attends' => true, ...$none],
        $foreign->id => ['attends' => true, ...$none],
    ]]));

    expect($professional->services()->pluck('services.id')->all())->toBe([$cut->id]);
});

test('a professional of another business cannot be edited, read or removed from here', function (): void {
    [, $piece] = proPieceBusiness();
    $stranger = Professional::factory()->create();

    expect(fn () => $piece->sheetOf($stranger->id))->toThrow(ModelNotFoundException::class)
        ->and(fn () => $piece->save(proPayload(), $stranger->id))->toThrow(ModelNotFoundException::class)
        ->and(fn () => $piece->delete($stranger->id))->toThrow(ModelNotFoundException::class)
        ->and($stranger->fresh())->not->toBeNull();
});

test('a professional with bookings to come cannot be removed, and nothing is cancelled', function (): void {
    [$business, $piece] = proPieceBusiness();
    $professional = $piece->save(proPayload());
    $booking = Appointment::factory()->create(['business_id' => $business->id, 'professional_id' => $professional->id]);

    expect(fn () => $piece->delete($professional->id))->toThrow(RuntimeException::class)
        ->and(Professional::count())->toBe(1)
        ->and($booking->fresh()->status->value)->toBe('confirmed');
});

test('a professional whose bookings are all past or cancelled can be removed, and the history stays', function (): void {
    [$business, $piece] = proPieceBusiness();
    $professional = $piece->save(proPayload());
    $past = Appointment::factory()->create(['business_id' => $business->id, 'professional_id' => $professional->id, 'starts_at' => now()->subDays(3), 'ends_at' => now()->subDays(3)->addMinutes(30)]);
    Appointment::factory()->cancelled()->create(['business_id' => $business->id, 'professional_id' => $professional->id]);

    $piece->delete($professional->id);

    expect(Professional::count())->toBe(0)
        ->and($past->fresh()->professional_id)->toBe($professional->id);
});

test('the sheet rules ask for a name that is not taken, and keep a deleted one out of the way', function (): void {
    [$business] = proPieceBusiness();
    $taken = $business->professionals()->create(['name' => 'Ana']);
    $gone = $business->professionals()->create(['name' => 'Luis']);
    $gone->delete();
    $other = Business::factory()->create();
    $other->professionals()->create(['name' => 'Marta']);

    $check = fn (string $name, ?int $ignoring = null): bool => Validator::make(
        proPayload(['name' => $name]),
        ProfessionalForm::fieldRules(true, (new ProfessionalDto)->week, $business->id, $ignoring),
    )->passes();

    expect($check('Ana'))->toBeFalse()
        ->and($check('Ana', $taken->id))->toBeTrue()
        ->and($check('Luis'))->toBeTrue()
        ->and($check('Marta'))->toBeTrue()
        ->and($check(''))->toBeFalse();
});

test('the sheet rules bound the capacity, the ceiling, the length and the price kind', function (): void {
    $rules = fn (array $overrides): bool => Validator::make(
        proPayload($overrides),
        ProfessionalForm::fieldRules(true, (new ProfessionalDto)->week, 1),
    )->passes();
    $row = fn (array $row): array => ['services' => [5 => ['attends' => true, 'duration_minutes' => null, 'price_type' => null, 'price' => null, 'deposit' => null, ...$row]]];

    expect($rules(['appointment_capacity' => '0']))->toBeFalse()
        ->and($rules(['appointment_capacity' => '51']))->toBeFalse()
        ->and($rules(['appointments_per_day' => '501']))->toBeFalse()
        ->and($rules(['appointment_capacity' => '2', 'appointments_per_day' => '8']))->toBeTrue()
        ->and($rules($row(['duration_minutes' => '0'])))->toBeFalse()
        ->and($rules($row(['duration_minutes' => '1441'])))->toBeFalse()
        ->and($rules($row(['price_type' => 'whatever'])))->toBeFalse()
        ->and($rules($row(['price' => 'abc'])))->toBeFalse()
        ->and($rules($row(['duration_minutes' => '45', 'price_type' => 'fixed', 'price' => '12.50'])))->toBeTrue();
});

test('an open day needs both hours, and a shift can close after midnight', function (): void {
    $check = fn (array $week): bool => Validator::make(
        proPayload(['uses_business_hours' => false, 'week' => $week]),
        ProfessionalForm::fieldRules(false, ProfessionalDto::fromArray(['week' => $week])->week, 1),
    )->passes();

    expect($check([1 => ['open' => true, 'opens_at' => '', 'closes_at' => '13:00']]))->toBeFalse()
        ->and($check([1 => ['open' => true, 'opens_at' => '09:00', 'closes_at' => '09:00']]))->toBeFalse()
        ->and($check([1 => ['open' => true, 'opens_at' => '22:00', 'closes_at' => '02:00']]))->toBeTrue()
        ->and($check([1 => ['open' => false, 'opens_at' => '', 'closes_at' => '']]))->toBeTrue();
});
