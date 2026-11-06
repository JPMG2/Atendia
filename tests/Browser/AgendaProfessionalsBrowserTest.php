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
        'name' => 'Salón Lumen',
        'timezone' => 'America/Argentina/Buenos_Aires',
        'appointments_enabled' => true,
        'appointment_slot_minutes' => 30,
    ]);

    // Open every day, so the screen has a real day whenever this runs.
    foreach (range(0, 6) as $weekday) {
        $business->hours()->create(['day_of_week' => $weekday, 'opens_at' => '08:00', 'closes_at' => '22:00']);
    }

    $cut = $business->services()->create(['name' => 'Corte', 'duration_minutes' => 30, 'is_bookable' => true]);
    $color = $business->services()->create(['name' => 'Color completo', 'duration_minutes' => 90, 'is_bookable' => true]);
    $ana = $business->professionals()->create(['name' => 'Ana Soto', 'sort_order' => 1]);
    $luis = $business->professionals()->create(['name' => 'Luis Paz', 'sort_order' => 2]);
    $ana->services()->attach([$cut->id, $color->id]);
    $luis->services()->attach($cut->id);

    $today = CarbonImmutable::now($business->localTimezone())->startOfDay();

    Appointment::factory()->create([
        'business_id' => $business->id,
        'customer_id' => Customer::factory()->create(['business_id' => $business->id, 'name' => 'Marta Gil'])->id,
        'service_id' => $cut->id,
        'professional_id' => $ana->id,
        'starts_at' => $today->setTime(18, 0)->utc(),
        'ends_at' => $today->setTime(18, 30)->utc(),
    ]);

    Customer::factory()->create(['business_id' => $business->id, 'name' => 'Pedro Sosa']);

    $user = User::factory()->create(['name' => 'María González']);
    $user->business()->associate($business)->save();
    $this->actingAs($user);
});

test('the owner picks whose agenda to look at and the day follows', function (): void {
    $page = visit('/agenda')->resize(1280, 900);

    $page->assertSee(__('agenda.filter.professional'))
        ->assertSee('con Ana Soto')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'agenda-filter-everybody');

    // The real path: type to narrow the list, then pick the one option still standing.
    $page->click('#if-professional')
        ->type('#if-professional', 'Luis')
        ->click('[role="option"]:has-text("Luis Paz")')
        ->wait(1)
        ->assertDontSee('con Ana Soto')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'agenda-filter-luis');

    $page->resize(390, 844)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->screenshot(filename: 'agenda-filter-mobile');
});

test('the day says with whom each booking is, and the sheet asks who attends once the service is picked', function (): void {
    $page = visit('/agenda')->resize(1280, 900);

    $page->assertSee('con Ana Soto')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'agenda-professionals-day')
        ->click(__('agenda.book'))
        ->assertSee(__('agenda.form.professional'))
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'agenda-professionals-sheet');

    $page->resize(390, 844)
        ->screenshot(filename: 'agenda-professionals-sheet-mobile')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();
});
