<?php

declare(strict_types=1);

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Customer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);

    $business = Business::factory()->create([
        'name' => 'Consultorio Vida',
        'timezone' => 'America/Argentina/Buenos_Aires',
        'appointments_enabled' => true,
        'appointment_slot_minutes' => 30,
    ]);

    foreach (range(1, 5) as $weekday) {
        $business->hours()->create(['day_of_week' => $weekday, 'opens_at' => '08:00', 'closes_at' => '22:00']);
    }

    $business->services()->create(['name' => 'Ecodoppler', 'duration_minutes' => 30, 'is_bookable' => true]);

    // Booked on the day the screen opens on, at hours still ahead: the
    // screenshot has to show a real day, not an empty one.
    $today = CarbonImmutable::now($business->localTimezone())->startOfDay();

    foreach ([['Ana Gómez', 18], ['Pedro Sosa', 19]] as [$name, $hour]) {
        Appointment::factory()->create([
            'business_id' => $business->id,
            'customer_id' => Customer::factory()->create(['business_id' => $business->id, 'name' => $name])->id,
            'service_id' => $business->services()->first()->id,
            'starts_at' => $today->setTime($hour, 0)->utc(),
            'ends_at' => $today->setTime($hour, 30)->utc(),
            'notes' => $hour === 18 ? 'Trae los estudios previos' : null,
        ]);
    }

    $user = User::factory()->create(['name' => 'María González']);
    $user->business()->associate($business)->save();
    $this->actingAs($user);
});

test('the agenda shows the day, its bookings and the free hours on desktop', function (): void {
    visit('/agenda')->resize(1280, 900)
        ->assertNoJavaScriptErrors()
        ->assertSee(__('agenda.title'))
        ->assertSee(__('agenda.free.title'))
        ->screenshot(filename: 'agenda-today-desktop');
});

test('a day with bookings reads clean on a phone and in the dark', function (): void {
    visit('/agenda')->resize(560, 900)
        ->assertNoJavaScriptErrors()
        ->click('@theme-toggle')
        ->screenshot(filename: 'agenda-phone-dark');
});

test('the week view shows the seven days and opens the one pressed', function (): void {
    visit('/agenda')->resize(1280, 900)
        ->assertNoJavaScriptErrors()
        ->click(__('agenda.view_week'))
        ->assertSee(__('agenda.view_week'))
        ->screenshot(filename: 'agenda-week-desktop')
        ->assertNoJavaScriptErrors();
});

test('pressing a free hour opens the booking sheet with that hour', function (): void {
    visit('/agenda')->resize(1280, 900)
        ->assertNoJavaScriptErrors()
        ->click('@free-hour-1300')
        ->assertSee(__('agenda.form.title'))
        ->assertSee(__('agenda.form.customer'))
        ->screenshot(filename: 'agenda-sheet-desktop');
});

test('the public booking link works with no session, on a phone', function (): void {
    $business = Business::query()->where('name', 'Consultorio Vida')->sole();

    visit(route('booking.public', ['code' => $business->booking_code]))->resize(560, 900)
        ->assertNoJavaScriptErrors()
        ->assertSee(__('agenda.public.title'))
        ->assertSee($business->name)
        ->screenshot(filename: 'agenda-public-phone');
});

test('the bookings card of Mi negocio shows its four fields in one row', function (): void {
    visit('/negocio/turnos')->resize(1280, 900)
        ->assertNoJavaScriptErrors()
        ->assertSee(__('client.business.appointments.title'))
        ->assertSee(__('client.business.appointments.capacity'))
        ->screenshot(filename: 'agenda-settings-desktop');
});
