<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\Country;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\CountryHolidaySeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The days she does not open, on the screen
|--------------------------------------------------------------------------
| The dates come from the house picker, which only exists once Flatpickr has
| run: a server render cannot prove that picking a day ends up as a closure.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    Queue::fake();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);

    $this->business = Business::factory()->create([
        'name' => 'Laboratorio Vida',
        'timezone' => 'America/Argentina/Buenos_Aires',
    ]);

    foreach (range(1, 5) as $weekday) {
        $this->business->hours()->create(['day_of_week' => $weekday, 'opens_at' => '09:00', 'closes_at' => '18:00']);
    }

    $user = User::factory()->create(['name' => 'Mariana Ortiz']);
    $user->business()->associate($this->business)->save();
    $this->actingAs($user);
});

test('a closure already loaded reads with its days and its reason', function (): void {
    $this->business->closures()->create([
        'starts_on' => now()->addMonth()->startOfMonth()->format('Y-m-d'),
        'ends_on' => now()->addMonth()->startOfMonth()->addDays(9)->format('Y-m-d'),
        'reason' => 'Vacaciones de verano',
    ]);

    visit(route('my-business.horarios'))->resize(1280, 1100)
        ->assertNoJavaScriptErrors()
        ->assertSee(__('client.business.hours.closures_title'))
        ->assertSee('Vacaciones de verano')
        ->screenshot(filename: 'closures-loaded');
});

test('the three ways a day can be different read together', function (): void {
    $this->seed(CurrencySeeder::class);
    $this->seed(CountrySeeder::class);
    $this->seed(CountryHolidaySeeder::class);
    $this->business->forceFill([
        'country_id' => Country::query()->where('code', 'ARG')->value('id'),
        'appointments_enabled' => true,
    ])->save();

    // A full close with somebody holding an hour, and a short day.
    $closed = $this->business->closures()->create([
        'starts_on' => now()->addMonth()->format('Y-m-d'),
        'ends_on' => now()->addMonth()->format('Y-m-d'),
        'reason' => 'Mudanza del local',
    ]);
    $this->business->closures()->create([
        'starts_on' => now()->addMonths(2)->format('Y-m-d'),
        'ends_on' => now()->addMonths(2)->format('Y-m-d'),
        'opens_at' => '09:00',
        'closes_at' => '13:00',
        'reason' => 'Nochebuena',
    ]);

    $customer = Customer::factory()->create(['business_id' => $this->business->id, 'phone' => '5493415557788']);
    Appointment::factory()->create([
        'business_id' => $this->business->id,
        'customer_id' => $customer->id,
        'starts_at' => $closed->starts_on->setTime(10, 0),
        'ends_at' => $closed->starts_on->setTime(11, 0),
        'status' => AppointmentStatus::Confirmed,
    ]);

    visit(route('my-business.horarios'))->resize(1280, 1200)
        ->assertNoJavaScriptErrors()
        ->assertSee('Mudanza del local')
        ->assertSee('Nochebuena')
        ->assertSee('09:00 – 13:00')
        ->assertSee(__('client.business.hours.closure_notify'))
        ->assertSee(__('client.business.hours.holidays_note'))
        ->screenshot(filename: 'closures-full');
});

test('picking a day on the calendar turns into a closure', function (): void {
    $page = visit(route('my-business.horarios'))->resize(1280, 1100);

    $page->assertSee(__('client.business.hours.closure_empty'))
        ->click('#if-range')
        ->assertVisible('.flatpickr-calendar.open')
        ->screenshotElement('.flatpickr-calendar.open', 'closures-calendar');

    // One click opens the range, a second closes it: that is what the picker
    // asks of her, and the hidden input is what the form reads.
    $page->click('.flatpickr-calendar.open .flatpickr-day.today')
        ->click('.flatpickr-calendar.open .flatpickr-day.today')
        ->fill('#if-reason', 'Feriado nacional')
        ->click('text='.__('client.business.hours.closure_add'))
        ->waitForText('Feriado nacional');

    $page->assertNoJavaScriptErrors()
        ->screenshot(filename: 'closures-added');

    expect($this->business->closures()->sole())
        ->reason->toBe('Feriado nacional')
        ->starts_on->format('Y-m-d')->toBe(now()->format('Y-m-d'));
});
