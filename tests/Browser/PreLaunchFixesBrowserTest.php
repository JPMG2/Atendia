<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The 2026-11-02 fixes — the shots a human looks at
|--------------------------------------------------------------------------
| The new switch sits with its brothers in the service sheet, the unlink
| button lives under the linked number, and the booking link says what the
| number is for.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
    $this->seed(PlanSeeder::class);
    config()->set('services.evolution.url', 'http://evolution.test');
    config()->set('services.evolution.key', 'test-key');
});

function fixesOwner(array $business = []): User
{
    $user = User::factory()->create();
    $user->assignRole('client');
    $user->business()->associate(Business::factory()->create(['name' => 'Consultorio Vida', ...$business]))->save();

    test()->actingAs($user->refresh());

    return $user;
}

test('the service sheet offers "Se reserva con turno" beside Destacado', function (int $width, bool $dark): void {
    $owner = fixesOwner();
    Service::factory()->create(['business_id' => $owner->business_id, 'name' => 'Consulta general', 'is_active' => true]);

    $page = visit('/servicios')->resize($width, 900);

    if ($dark) {
        $page->click('@theme-toggle');
    }

    $page->click('[aria-label="Editar Consulta general"]')
        ->assertNoJavaScriptErrors()
        ->assertSee(__('client.services.bookable'))
        ->assertSee(__('client.services.bookable_help'))
        // The new switch is the last field of the sheet: scroll the panel itself down to it.
        ->script('document.querySelectorAll("div").forEach((el) => { if (el.scrollHeight > el.clientHeight + 40 && getComputedStyle(el).overflowY !== "visible") { el.scrollTop = el.scrollHeight; } })');

    $page->screenshot(filename: 'fixes-service-bookable-'.$width.'-'.($dark ? 'dark' : 'light'));
})->with([[1280, false], [390, true]]);

test('a linked number can be unlinked from its own card, after one question', function (int $width, bool $dark): void {
    Http::fake(['http://evolution.test/*' => Http::response(['instance' => ['state' => 'open']])]);
    fixesOwner(['whatsapp_instance' => 'business-7', 'whatsapp_connected_at' => now()]);

    $page = visit('/whatsapp')->resize($width, 900);

    if ($dark) {
        $page->click('@theme-toggle');
    }

    $page->assertNoJavaScriptErrors()
        ->assertSee(__('whatsapp.disconnect.button'))
        ->screenshot(filename: 'fixes-whatsapp-unlink-'.$width.'-'.($dark ? 'dark' : 'light'))
        ->click(__('whatsapp.disconnect.button'))
        ->assertSee(__('whatsapp.disconnect.title'))
        ->assertSee(__('whatsapp.disconnect.accept'))
        ->screenshot(filename: 'fixes-whatsapp-unlink-ask-'.$width.'-'.($dark ? 'dark' : 'light'));
})->with([[1280, false], [390, true]]);

test('after booking, the person turns the reminder on from their own WhatsApp', function (int $width, bool $dark): void {
    $owner = fixesOwner(['appointments_enabled' => true, 'timezone' => 'America/Caracas', 'whatsapp_instance' => 'negocio-7', 'whatsapp_connected_at' => now()]);
    Cache::put('wa:profile:'.$owner->business_id, ['number' => '584121234567', 'name' => 'Consultorio Vida', 'picture' => null], 3600);

    foreach (range(0, 6) as $weekday) {
        $owner->business->hours()->create(['day_of_week' => $weekday, 'opens_at' => '00:00', 'closes_at' => '23:30']);
    }

    auth()->logout();

    $page = visit('/reservar/'.$owner->business->booking_code)->resize($width, 900);

    if ($dark) {
        $page->script('document.documentElement.classList.add("dark")');
    }

    $page->assertNoJavaScriptErrors()
        ->assertSee(__('agenda.public.phone_hint'))
        ->click('.ag-slot >> nth=-1')
        ->fill('name', 'Ana Gómez')
        ->fill('phone', '584149998877')
        ->click(__('agenda.public.submit'))
        ->assertSee(__('agenda.public.done_title'))
        ->assertSee(__('agenda.public.reminder_button'))
        ->screenshot(filename: 'fixes-booking-done-'.$width.'-'.($dark ? 'dark' : 'light'));
})->with([[390, false], [1280, true]]);
