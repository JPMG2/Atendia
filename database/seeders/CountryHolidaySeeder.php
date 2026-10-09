<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Country;
use App\Models\CountryHoliday;
use Illuminate\Database\Seeder;

/**
 * The national holidays a business can load in one click.
 *
 * Only what can be stated without guessing: the fixed dates, and the ones
 * counted from Easter. The ones a government moves by decree each year (the
 * Argentine "trasladables", the bridges) are NOT here — the screen says so,
 * and she adds those by hand.
 */
class CountryHolidaySeeder extends Seeder
{
    /**
     * Keyed by ISO code. `[month, day]` for a fixed date, `['easter' => n]`
     * for a day counted from Easter Sunday.
     *
     * @var array<string, array<string, array<string, int>|array{int, int}>>
     */
    private const array HOLIDAYS = [
        'ARG' => [
            'Año Nuevo' => [1, 1],
            'Carnaval (lunes)' => ['easter' => -48],
            'Carnaval (martes)' => ['easter' => -47],
            'Día de la Memoria por la Verdad y la Justicia' => [3, 24],
            'Viernes Santo' => ['easter' => -2],
            'Día del Veterano y de los Caídos en Malvinas' => [4, 2],
            'Día del Trabajador' => [5, 1],
            'Día de la Revolución de Mayo' => [5, 25],
            'Paso a la Inmortalidad del General Belgrano' => [6, 20],
            'Día de la Independencia' => [7, 9],
            'Inmaculada Concepción de María' => [12, 8],
            'Navidad' => [12, 25],
        ],
        'VEN' => [
            'Año Nuevo' => [1, 1],
            'Carnaval (lunes)' => ['easter' => -48],
            'Carnaval (martes)' => ['easter' => -47],
            'Jueves Santo' => ['easter' => -3],
            'Viernes Santo' => ['easter' => -2],
            'Declaración de la Independencia' => [4, 19],
            'Día del Trabajador' => [5, 1],
            'Batalla de Carabobo' => [6, 24],
            'Día de la Independencia' => [7, 5],
            'Natalicio de Simón Bolívar' => [7, 24],
            'Día de la Resistencia Indígena' => [10, 12],
            'Navidad' => [12, 25],
        ],
    ];

    public function run(): void
    {
        foreach (self::HOLIDAYS as $code => $holidays) {
            $country = Country::query()->where('code', $code)->first();

            // A country with any holiday already belongs to the Holidays master: a re-run must not
            // put back a day she renamed, switched off, or replaced.
            if ($country === null || CountryHoliday::query()->where('country_id', $country->id)->exists()) {
                continue;
            }

            foreach ($holidays as $name => $when) {
                CountryHoliday::query()->updateOrCreate(
                    ['country_id' => $country->id, 'name' => $name],
                    [
                        'month' => $when[0] ?? null,
                        'day' => $when[1] ?? null,
                        'easter_offset' => $when['easter'] ?? null,
                    ],
                );
            }
        }
    }
}
