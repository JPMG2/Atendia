<?php

declare(strict_types=1);

use App\Models\Appointment;
use App\Models\Business;
use App\Models\ProfessionalHour;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed([RolesAndPermissionsSeeder::class, MenuSeeder::class]);

    $this->business = Business::factory()->create(['appointments_enabled' => true]);
    $this->owner = User::factory()->create(['business_id' => $this->business->id, 'name' => 'Carla Méndez']);

    $cut = $this->business->services()->create(['name' => 'Corte', 'duration_minutes' => 30, 'is_bookable' => true, 'price' => 12000]);
    $color = $this->business->services()->create(['name' => 'Color completo', 'duration_minutes' => 90, 'is_bookable' => true, 'price' => 40000]);

    $this->ana = $this->business->professionals()->create(['name' => 'Ana Soto', 'appointments_per_day' => 8]);
    foreach ([[1, '09:00', '13:00'], [3, '14:00', '18:00']] as [$day, $opens, $closes]) {
        ProfessionalHour::factory()->create(['business_id' => $this->business->id, 'professional_id' => $this->ana->id, 'day_of_week' => $day, 'opens_at' => $opens, 'closes_at' => $closes]);
    }
    $this->ana->services()->attach($cut->id, ['duration_minutes' => 20, 'price_type' => 'from', 'price' => 9000]);
    $this->ana->services()->attach($color->id);

    $this->business->professionals()->create(['name' => 'Luis Paz', 'is_active' => false]);
});

test('the owner sees the professionals, edits one and adds another, and nothing overflows a phone', function (): void {
    Appointment::factory()->count(2)->create(['business_id' => $this->business->id, 'professional_id' => $this->ana->id]);
    $this->actingAs($this->owner);

    $page = visit('/profesionales')->resize(1280, 900);

    $page->assertSee('Ana Soto')
        ->assertSee('2 turnos por venir')
        ->assertSee('Luis Paz')
        ->assertSee(__('professionals.list.inactive'))
        ->assertSee(__('professionals.list.business_hours'))
        ->assertSee(__('professionals.list.limits', ['per_day' => 8]))
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'professionals-desktop')
        ->click('[aria-label="'.__('professionals.list.edit', ['name' => 'Ana Soto']).'"]')
        ->assertSee(__('professionals.hours.label'))
        ->assertSee(__('professionals.services.label'))
        ->assertSee('Color completo')
        ->screenshot(filename: 'professionals-sheet-desktop')
        ->click(__('professionals.sheet.cancel'))
        ->click(__('professionals.list.new'))
        ->type('#if-name', 'Marta Gil')
        ->click(__('professionals.sheet.save'))
        ->assertSee('Marta Gil')
        ->assertNoJavaScriptErrors();

    expect($this->business->professionals()->where('name', 'Marta Gil')->exists())->toBeTrue();

    $page->resize(390, 844)
        ->screenshot(filename: 'professionals-mobile')
        ->click('[aria-label="'.__('professionals.list.edit', ['name' => 'Ana Soto']).'"]')
        ->assertSee(__('professionals.hours.label'))
        ->screenshot(filename: 'professionals-sheet-mobile')
        ->script("document.querySelector('.slide-over-body').scrollTo(0, 9999)");

    $page->wait(0.3)
        ->screenshot(filename: 'professionals-sheet-mobile-services')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();
});

test('removing one with bookings to come asks first and is refused with the reason', function (): void {
    Appointment::factory()->create(['business_id' => $this->business->id, 'professional_id' => $this->ana->id]);
    $this->actingAs($this->owner);

    visit('/profesionales')->resize(1280, 900)
        ->click('[aria-label="'.__('professionals.list.edit', ['name' => 'Ana Soto']).'"]')
        ->click('[aria-label="'.__('professionals.sheet.delete').'"]')
        ->assertSee(__('professionals.sheet.delete_title', ['name' => 'Ana Soto']))
        ->click(__('professionals.sheet.delete'))
        ->assertSee(__('professionals.notify.has_bookings'))
        ->assertNoJavaScriptErrors();

    expect($this->business->professionals()->where('name', 'Ana Soto')->exists())->toBeTrue();
});
