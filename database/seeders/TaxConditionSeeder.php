<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Country;
use App\Models\TaxCondition;
use Illuminate\Database\Seeder;

class TaxConditionSeeder extends Seeder
{
    /**
     * Tax standings per country, keyed by the country's `code`. Each one is unique
     * WITHIN its country.
     *
     * @var array<string, list<array{name: string, code: string, discriminate_tax: bool}>>
     */
    private array $conditionsByCountry = [
        // Argentina: standing before VAT (AFIP).
        'ARG' => [
            ['name' => 'Responsable Inscripto', 'code' => 'RI', 'discriminate_tax' => true],
            ['name' => 'IVA Exento', 'code' => 'EX', 'discriminate_tax' => false],
            ['name' => 'Monotributista', 'code' => 'MT', 'discriminate_tax' => false],
            ['name' => 'IVA No Alcanzado', 'code' => 'NA', 'discriminate_tax' => false],
            ['name' => 'Consumidor Final', 'code' => 'CF', 'discriminate_tax' => false],
        ],

        // Venezuela: taxpayer category (SENIAT). VAT and income tax are taxes,
        // not standings, so they do not belong here.
        'VEN' => [
            ['name' => 'Contribuyente Ordinario', 'code' => 'CO', 'discriminate_tax' => true],
            ['name' => 'Contribuyente Especial', 'code' => 'CE', 'discriminate_tax' => true],
            ['name' => 'Contribuyente Formal', 'code' => 'CFO', 'discriminate_tax' => false],
            ['name' => 'Persona Natural', 'code' => 'PN', 'discriminate_tax' => false],
            ['name' => 'No Contribuyente', 'code' => 'NC', 'discriminate_tax' => false],
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->conditionsByCountry as $countryCode => $conditions) {
            $countryId = Country::query()->where('code', $countryCode)->value('id');

            if ($countryId === null) {
                continue;
            }

            foreach ($conditions as $condition) {
                TaxCondition::query()->firstOrCreate(
                    ['country_id' => $countryId, 'code' => $condition['code']],
                    [
                        'name' => $condition['name'],
                        'discriminate_tax' => $condition['discriminate_tax'],
                    ],
                );
            }
        }
    }
}
