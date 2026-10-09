<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\CatalogFormSeeder;
use Database\Seeders\CountryHolidaySeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The Holidays master, in a real browser
|--------------------------------------------------------------------------
| The list she reads to see what each country has, and the form that asks
| only for the date of the shape she picked — the one-year holiday last.
*/

beforeEach(function (): void {
    app()->setLocale('es');

    foreach ([RolesAndPermissionsSeeder::class, CatalogFormSeeder::class, CurrencySeeder::class,
        CountrySeeder::class, CountryHolidaySeeder::class] as $seeder) {
        $this->seed($seeder);
    }

    $admin = User::factory()->create();
    $admin->syncRoles('admin');
    $this->actingAs($admin);
});

test('the list says each day in words, with its shape', function (): void {
    $page = visit('/admin/catalogs')->resize(1280, 900);

    $page->click('Feriados')->wait(1)
        ->assertNoJavaScriptErrors()
        ->assertSee('Día de la Independencia')
        ->assertSee('9 de julio')
        ->assertSee('2 días antes de Pascua')
        ->assertSee('Desde Pascua')
        ->screenshot(filename: 'catalog-holidays-list');
});

test('the form asks only for the date of the chosen shape', function (string $label, int $width, bool $dark): void {
    $page = visit('/admin/catalogs')->resize($width, 900);

    if ($dark) {
        $page->click('@theme-toggle');
    }

    $page->click('Feriados')->wait(0.8)->click('Crear feriado')->wait(1);

    // A new holiday opens as "every year": month and day are asked, the Easter number and the date are not.
    expect((bool) $page->script('document.querySelector("[name=month]").closest(".form-row").offsetParent !== null'))->toBeTrue()
        ->and((bool) $page->script('document.querySelector("[name=easter_offset]").closest(".form-row").offsetParent === null'))->toBeTrue()
        ->and((bool) $page->script('document.querySelector("#if-on_date").closest(".form-row").offsetParent === null'))->toBeTrue();

    $page->screenshot(filename: 'catalog-holidays-form-fixed-'.$label);

    // Picking "one year only" swaps the row for the calendar.
    $page->script('$wire = Livewire.all()[Livewire.all().length - 1].$wire; $wire.form.data.kind = "once";');
    $page->wait(0.5);

    expect((bool) $page->script('document.querySelector("#if-on_date").closest(".form-row").offsetParent !== null'))->toBeTrue()
        ->and((bool) $page->script('document.querySelector("[name=month]").closest(".form-row").offsetParent === null'))->toBeTrue();

    $page->assertNoJavaScriptErrors()->screenshot(filename: 'catalog-holidays-form-once-'.$label);

    expect((int) $page->script('document.documentElement.scrollWidth - window.innerWidth'))->toBeLessThanOrEqual(0);
})->with([
    'desktop' => ['desktop', 1280, false],
    'dark' => ['dark', 1280, true],
    'phone' => ['phone', 390, false],
]);
