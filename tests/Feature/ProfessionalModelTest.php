<?php

declare(strict_types=1);

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Professional;
use App\Models\ProfessionalHour;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a professional belongs to its business and a signed-in business only sees its own', function (): void {
    $mine = Business::factory()->create();
    $theirs = Business::factory()->create();
    Professional::factory()->create(['business_id' => $mine->id, 'name' => 'Laura Ríos']);
    Professional::factory()->create(['business_id' => $theirs->id, 'name' => 'Marcos Pérez']);

    $this->actingAs(User::factory()->create(['business_id' => $mine->id]));

    expect(Professional::pluck('name')->all())->toBe(['Laura Ríos'])
        ->and($mine->professionals()->count())->toBe(1);
});

test('a professional created through the business gets stamped with it', function (): void {
    $business = Business::factory()->create();

    $professional = $business->professionals()->create(['name' => 'Ana Soto']);

    expect($professional->business_id)->toBe($business->id)
        ->and($professional->fresh()->is_active)->toBeTrue();
});

test('only active professionals are offered, in the owner order', function (): void {
    $business = Business::factory()->create();
    Professional::factory()->create(['business_id' => $business->id, 'name' => 'Segunda', 'sort_order' => 2]);
    Professional::factory()->create(['business_id' => $business->id, 'name' => 'Primera', 'sort_order' => 1]);
    Professional::factory()->inactive()->create(['business_id' => $business->id, 'name' => 'De baja']);

    expect(Professional::query()->bookable()->pluck('name')->all())->toBe(['Primera', 'Segunda']);
});

test('the capacity and the daily ceiling are empty until the owner sets them', function (): void {
    $professional = Professional::factory()->create();

    expect($professional->appointment_capacity)->toBeNull()
        ->and($professional->appointments_per_day)->toBeNull();
});

test('a professional keeps its own length and price per service, and the service sees the same row', function (): void {
    $business = Business::factory()->create();
    $professional = Professional::factory()->create(['business_id' => $business->id]);
    $service = Service::factory()->create(['business_id' => $business->id, 'duration_minutes' => 30]);

    $professional->services()->attach($service->id, ['duration_minutes' => 45, 'price_type' => 'from', 'price' => 20, 'deposit' => 5]);

    $pivot = $professional->services()->first()->pivot;

    expect($pivot->duration_minutes)->toBe(45)
        ->and($pivot->price_type)->toBe('from')
        ->and((float) $pivot->price)->toBe(20.0)
        ->and($service->professionals()->first()->is($professional))->toBeTrue();
});

test('a service attached without overrides inherits: every override stays empty', function (): void {
    $professional = Professional::factory()->create();
    $service = Service::factory()->create(['business_id' => $professional->business_id]);

    $professional->services()->attach($service->id);

    $pivot = $professional->services()->first()->pivot;

    expect($pivot->duration_minutes)->toBeNull()
        ->and($pivot->price)->toBeNull();
});

test('their shifts are fenced by business and sorted by day and hour', function (): void {
    $mine = Business::factory()->create();
    $professional = Professional::factory()->create(['business_id' => $mine->id]);
    ProfessionalHour::factory()->create(['business_id' => $mine->id, 'professional_id' => $professional->id, 'day_of_week' => 3, 'opens_at' => '09:00', 'closes_at' => '12:00']);
    ProfessionalHour::factory()->create(['business_id' => $mine->id, 'professional_id' => $professional->id, 'day_of_week' => 1, 'opens_at' => '14:00', 'closes_at' => '18:00']);
    ProfessionalHour::factory()->create(['business_id' => $mine->id, 'professional_id' => $professional->id, 'day_of_week' => 1, 'opens_at' => '09:00', 'closes_at' => '13:00']);

    $other = Business::factory()->create();
    ProfessionalHour::factory()->create(['business_id' => $other->id]);

    $this->actingAs(User::factory()->create(['business_id' => $mine->id]));

    expect($professional->hours->map(fn (ProfessionalHour $hour): string => $hour->day_of_week.' '.substr((string) $hour->opens_at, 0, 5))->all())
        ->toBe(['1 09:00', '1 14:00', '3 09:00'])
        ->and(ProfessionalHour::count())->toBe(3);
});

test('a booking can name who attends it, and an old one stays unassigned', function (): void {
    $business = Business::factory()->create();
    $professional = Professional::factory()->create(['business_id' => $business->id]);

    $assigned = Appointment::factory()->create(['business_id' => $business->id, 'professional_id' => $professional->id]);
    $old = Appointment::factory()->create(['business_id' => $business->id]);

    expect($assigned->professional->is($professional))->toBeTrue()
        ->and($old->professional_id)->toBeNull()
        ->and($old->professional)->toBeNull()
        ->and($professional->appointments()->count())->toBe(1);
});

test('removing a professional keeps its bookings as history', function (): void {
    $business = Business::factory()->create();
    $professional = Professional::factory()->create(['business_id' => $business->id]);
    $booking = Appointment::factory()->create(['business_id' => $business->id, 'professional_id' => $professional->id]);

    $professional->delete();

    expect(Professional::count())->toBe(0)
        ->and(Professional::withTrashed()->count())->toBe(1)
        ->and($booking->fresh()->professional_id)->toBe($professional->id);
});

test('deleting a business takes its professionals and their shifts with it', function (): void {
    $business = Business::factory()->create();
    $professional = Professional::factory()->create(['business_id' => $business->id]);
    ProfessionalHour::factory()->create(['business_id' => $business->id, 'professional_id' => $professional->id]);

    $business->forceDelete();

    expect(Professional::withTrashed()->count())->toBe(0)
        ->and(ProfessionalHour::count())->toBe(0);
});
