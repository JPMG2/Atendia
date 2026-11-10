<?php

declare(strict_types=1);

use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->setLocale('es');

    $this->business = Business::factory()->create(['name' => 'Salón Lumen', 'timezone' => 'America/Argentina/Buenos_Aires', 'appointments_enabled' => true, 'appointment_slot_minutes' => 30]);

    foreach (range(0, 6) as $weekday) {
        $this->business->hours()->create(['day_of_week' => $weekday, 'opens_at' => '09:00', 'closes_at' => '20:00']);
    }

    $cut = $this->business->services()->create(['name' => 'Corte', 'duration_minutes' => 30, 'is_bookable' => true]);
    $this->business->professionals()->create(['name' => 'Ana Soto', 'sort_order' => 1])->services()->attach($cut->id);
    $this->business->professionals()->create(['name' => 'Luis Paz', 'sort_order' => 2])->services()->attach($cut->id);
});

test('the public link asks who should attend, and books and says who will', function (): void {
    $page = visit('/reservar/'.$this->business->booking_code)->resize(1280, 900);

    $page->assertSee(__('agenda.form.professional'))
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'public-booking-professionals-desktop')
        // The real path with the mouse: the second person on the list, not the first.
        ->click('#if-professional_id')
        ->type('#if-professional_id', 'Luis')
        ->click('[role="option"]:has-text("Luis Paz")')
        ->wait(1)
        ->type('#if-name', 'Marta Gil')
        ->type('#if-phone', '5491155550199')
        ->click('.ag-slot:first-child')
        ->click(__('agenda.public.submit'))
        ->assertSee(__('agenda.public.done_title'))
        ->assertSee('Te atiende Luis Paz')
        ->assertSee(__('agenda.public.calendar_button'))
        ->assertSee(__('agenda.public.calendar_file_button'))
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'public-booking-professionals-done');

    $page->resize(390, 844)
        ->screenshot(filename: 'public-booking-professionals-done-mobile')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true);
});

test('the link on a phone keeps the picker inside the card', function (): void {
    visit('/reservar/'.$this->business->booking_code)->resize(390, 844)
        ->assertSee(__('agenda.form.professional'))
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'public-booking-professionals-mobile')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true);
});
