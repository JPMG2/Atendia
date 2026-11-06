<?php

declare(strict_types=1);

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Professional;
use App\Models\ProfessionalHour;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Who attends, on the public booking link
|--------------------------------------------------------------------------
| The visitor may name who they want or leave it empty; the hours follow the
| person, and the confirmation says who will attend them.
*/

beforeEach(function (): void {
    app()->setLocale('es');
});

/** A business open 09:00-20:00 every day with the agenda on and a corte that takes a booking. */
function linkBusiness(): Business
{
    $business = Business::factory()->create(['name' => 'Salón Lumen', 'timezone' => 'America/Argentina/Buenos_Aires', 'appointments_enabled' => true, 'appointment_slot_minutes' => 30]);

    foreach (range(0, 6) as $weekday) {
        $business->hours()->create(['day_of_week' => $weekday, 'opens_at' => '09:00', 'closes_at' => '20:00']);
    }

    $business->services()->create(['name' => 'Corte', 'duration_minutes' => 30, 'is_bookable' => true]);
    $business->services()->create(['name' => 'Color', 'duration_minutes' => 30, 'is_bookable' => true]);

    return $business;
}

function linkTomorrow(Business $business): string
{
    return CarbonImmutable::now($business->localTimezone())->addDay()->format('Y-m-d');
}

function linkPage(Business $business)
{
    return livewire('booking.public', ['code' => $business->booking_code])->set('form.day', linkTomorrow($business));
}

test('a business with nobody set up shows no picker and no "te atiende"', function (): void {
    $business = linkBusiness();

    linkPage($business)
        ->assertDontSee(__('agenda.form.professional'))
        ->call('pick', '10:00')
        ->set('form.name', 'Ana Gómez')->set('form.phone', '5491155550101')
        ->call('book')
        ->assertSee(__('agenda.public.done_title'))
        ->assertDontSee('Te atiende');
});

test('the picker lists who attends the service and follows it when the service changes', function (): void {
    $business = linkBusiness();
    $cut = $business->services()->where('name', 'Corte')->value('id');
    $color = $business->services()->where('name', 'Color')->value('id');
    $ana = $business->professionals()->create(['name' => 'Ana Soto', 'sort_order' => 1]);
    $luis = $business->professionals()->create(['name' => 'Luis Paz', 'sort_order' => 2]);
    $ana->services()->attach([$cut, $color]);
    $luis->services()->attach($cut);

    $page = linkPage($business)->set('form.service_id', $cut);

    expect($page->instance()->professionalOptions)->toBe([$ana->id => 'Ana Soto', $luis->id => 'Luis Paz']);

    $page->assertSee(__('agenda.form.professional'))
        ->set('form.professional_id', $luis->id)
        ->set('form.service_id', $color)
        ->assertSet('form.professional_id', null);

    expect($page->instance()->professionalOptions)->toBe([$ana->id => 'Ana Soto']);
});

test('the hours offered follow the professional picked', function (): void {
    $business = linkBusiness();
    $ana = $business->professionals()->create(['name' => 'Ana Soto']);
    $business->professionals()->create(['name' => 'Luis Paz']);
    $business->hours()->get();
    $weekday = (int) CarbonImmutable::now($business->localTimezone())->addDay()->format('w');
    ProfessionalHour::factory()->create(['business_id' => $business->id, 'professional_id' => $ana->id, 'day_of_week' => $weekday, 'opens_at' => '09:00', 'closes_at' => '10:00']);

    $page = linkPage($business);

    expect(array_keys($page->instance()->freeHours))->toHaveCount(22);

    $page->set('form.professional_id', $ana->id);

    expect(array_keys($page->instance()->freeHours))->toBe(['09:00', '09:30']);
});

test('booking with a chosen professional stores them and the confirmation says who attends', function (): void {
    $business = linkBusiness();
    $business->professionals()->create(['name' => 'Ana Soto', 'sort_order' => 1]);
    $luis = $business->professionals()->create(['name' => 'Luis Paz', 'sort_order' => 2]);

    linkPage($business)
        ->set('form.professional_id', $luis->id)
        ->call('pick', '10:00')
        ->set('form.name', 'Marta Gil')->set('form.phone', '5491155550102')
        ->call('book')
        ->assertHasNoErrors()
        ->assertSee(__('agenda.public.done_with', ['name' => 'Luis Paz']));

    expect(Appointment::withoutGlobalScopes()->sole()->professional_id)->toBe($luis->id);
});

test('leaving it empty lets the first free professional take it, and says who', function (): void {
    $business = linkBusiness();
    $ana = $business->professionals()->create(['name' => 'Ana Soto', 'sort_order' => 1]);
    $business->professionals()->create(['name' => 'Luis Paz', 'sort_order' => 2]);

    linkPage($business)
        ->call('pick', '10:00')
        ->set('form.name', 'Marta Gil')->set('form.phone', '5491155550103')
        ->call('book')
        ->assertSee(__('agenda.public.done_with', ['name' => 'Ana Soto']));

    expect(Appointment::withoutGlobalScopes()->sole()->professional_id)->toBe($ana->id);
});

test('a professional who is switched off, of another business, or who does not attend the service is never booked', function (): void {
    $business = linkBusiness();
    $cut = $business->services()->where('name', 'Corte')->value('id');
    $color = $business->services()->where('name', 'Color')->value('id');
    $ana = $business->professionals()->create(['name' => 'Ana Soto']);
    $ana->services()->attach($cut);
    $off = $business->professionals()->create(['name' => 'Luis Paz', 'is_active' => false]);
    $stranger = Professional::factory()->create();

    $try = fn (int $id, ?int $service) => linkPage($business)
        ->set('form.service_id', $service)
        ->set('form.professional_id', $id)
        ->call('pick', '10:00')
        ->set('form.name', 'Marta Gil')->set('form.phone', '5491155550104')
        ->call('book');

    $try($off->id, null)->assertHasErrors(['professional_id']);
    $try($ana->id, $color)->assertSee(__('agenda.public.professional_gone'));

    // Another business's id is not even found: the page answers 404, as for any forged value.
    linkPage($business)->set('form.professional_id', $stranger->id)->assertStatus(404);

    expect(Appointment::withoutGlobalScopes()->count())->toBe(0);
});

test('two visitors cannot take the same hour with the same professional, but can with different ones', function (): void {
    $business = linkBusiness();
    $ana = $business->professionals()->create(['name' => 'Ana Soto', 'sort_order' => 1]);
    $luis = $business->professionals()->create(['name' => 'Luis Paz', 'sort_order' => 2]);
    $book = fn (int $professional, string $phone) => linkPage($business)
        ->set('form.professional_id', $professional)
        ->call('pick', '10:00')
        ->set('form.name', 'Marta Gil')->set('form.phone', $phone)
        ->call('book');

    $book($ana->id, '5491155550105')->assertSee(__('agenda.public.done_title'));
    $book($ana->id, '5491155550106')->assertSee(__('agenda.public.taken'));
    $book($luis->id, '5491155550107')->assertSee(__('agenda.public.done_title'));

    expect(Appointment::withoutGlobalScopes()->count())->toBe(2);
});
