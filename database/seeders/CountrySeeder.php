<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Currency;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $countries = [
            ['name' => 'Argentina', 'code' => 'ARG', 'iso2' => 'AR', 'phone_code' => '54', 'currency' => 'ARS'],
            ['name' => 'Bolivia', 'code' => 'BOL', 'iso2' => 'BO', 'phone_code' => '591', 'currency' => 'BOB'],
            ['name' => 'Brasil', 'code' => 'BRA', 'iso2' => 'BR', 'phone_code' => '55', 'currency' => 'BRL'],
            ['name' => 'Canadá', 'code' => 'CAN', 'iso2' => 'CA', 'phone_code' => '1', 'currency' => 'CAD'],
            ['name' => 'Chile', 'code' => 'CHL', 'iso2' => 'CL', 'phone_code' => '56', 'currency' => 'CLP'],
            ['name' => 'Colombia', 'code' => 'COL', 'iso2' => 'CO', 'phone_code' => '57', 'currency' => 'COP'],
            ['name' => 'Costa Rica', 'code' => 'CRI', 'iso2' => 'CR', 'phone_code' => '506', 'currency' => 'CRC'],
            ['name' => 'Cuba', 'code' => 'CUB', 'iso2' => 'CU', 'phone_code' => '53', 'currency' => 'CUP'],
            ['name' => 'Ecuador', 'code' => 'ECU', 'iso2' => 'EC', 'phone_code' => '593', 'currency' => 'USD'],
            ['name' => 'El Salvador', 'code' => 'SLV', 'iso2' => 'SV', 'phone_code' => '503', 'currency' => 'USD'],
            ['name' => 'Estados Unidos', 'code' => 'USA', 'iso2' => 'US', 'phone_code' => '1', 'currency' => 'USD'],
            ['name' => 'Guatemala', 'code' => 'GTM', 'iso2' => 'GT', 'phone_code' => '502', 'currency' => 'GTQ'],
            ['name' => 'Honduras', 'code' => 'HND', 'iso2' => 'HN', 'phone_code' => '504', 'currency' => 'HNL'],
            ['name' => 'México', 'code' => 'MEX', 'iso2' => 'MX', 'phone_code' => '52', 'currency' => 'MXN'],
            ['name' => 'Nicaragua', 'code' => 'NIC', 'iso2' => 'NI', 'phone_code' => '505', 'currency' => 'NIO'],
            ['name' => 'Panamá', 'code' => 'PAN', 'iso2' => 'PA', 'phone_code' => '507', 'currency' => 'PAB'],
            ['name' => 'Paraguay', 'code' => 'PRY', 'iso2' => 'PY', 'phone_code' => '595', 'currency' => 'PYG'],
            ['name' => 'Perú', 'code' => 'PER', 'iso2' => 'PE', 'phone_code' => '51', 'currency' => 'PEN'],
            ['name' => 'Puerto Rico', 'code' => 'PRI', 'iso2' => 'PR', 'phone_code' => '1787', 'currency' => 'USD'],
            ['name' => 'República Dominicana', 'code' => 'DOM', 'iso2' => 'DO', 'phone_code' => '1809', 'currency' => 'DOP'],
            ['name' => 'Uruguay', 'code' => 'URY', 'iso2' => 'UY', 'phone_code' => '598', 'currency' => 'UYU'],
            ['name' => 'Venezuela', 'code' => 'VEN', 'iso2' => 'VE', 'phone_code' => '58', 'currency' => 'VES'],
        ];

        foreach ($countries as $country) {
            $currencyId = Currency::query()->where('code', $country['currency'])->value('id');

            Country::query()->firstOrCreate(
                ['code' => $country['code']],
                [
                    'name' => $country['name'],
                    'iso2' => $country['iso2'],
                    'phone_code' => $country['phone_code'],
                    'currency_id' => $currencyId,
                ],
            );
        }
    }
}
