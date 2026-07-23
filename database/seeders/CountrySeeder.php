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
            ['name' => 'Argentina', 'code' => 'ARG', 'phone_code' => '54', 'currency' => 'ARS'],
            ['name' => 'Bolivia', 'code' => 'BOL', 'phone_code' => '591', 'currency' => 'BOB'],
            ['name' => 'Brasil', 'code' => 'BRA', 'phone_code' => '55', 'currency' => 'BRL'],
            ['name' => 'Canadá', 'code' => 'CAN', 'phone_code' => '1', 'currency' => 'CAD'],
            ['name' => 'Chile', 'code' => 'CHL', 'phone_code' => '56', 'currency' => 'CLP'],
            ['name' => 'Colombia', 'code' => 'COL', 'phone_code' => '57', 'currency' => 'COP'],
            ['name' => 'Costa Rica', 'code' => 'CRI', 'phone_code' => '506', 'currency' => 'CRC'],
            ['name' => 'Cuba', 'code' => 'CUB', 'phone_code' => '53', 'currency' => 'CUP'],
            ['name' => 'Ecuador', 'code' => 'ECU', 'phone_code' => '593', 'currency' => 'USD'],
            ['name' => 'El Salvador', 'code' => 'SLV', 'phone_code' => '503', 'currency' => 'USD'],
            ['name' => 'Estados Unidos', 'code' => 'USA', 'phone_code' => '1', 'currency' => 'USD'],
            ['name' => 'Guatemala', 'code' => 'GTM', 'phone_code' => '502', 'currency' => 'GTQ'],
            ['name' => 'Honduras', 'code' => 'HND', 'phone_code' => '504', 'currency' => 'HNL'],
            ['name' => 'México', 'code' => 'MEX', 'phone_code' => '52', 'currency' => 'MXN'],
            ['name' => 'Nicaragua', 'code' => 'NIC', 'phone_code' => '505', 'currency' => 'NIO'],
            ['name' => 'Panamá', 'code' => 'PAN', 'phone_code' => '507', 'currency' => 'PAB'],
            ['name' => 'Paraguay', 'code' => 'PRY', 'phone_code' => '595', 'currency' => 'PYG'],
            ['name' => 'Perú', 'code' => 'PER', 'phone_code' => '51', 'currency' => 'PEN'],
            ['name' => 'Puerto Rico', 'code' => 'PRI', 'phone_code' => '1787', 'currency' => 'USD'],
            ['name' => 'República Dominicana', 'code' => 'DOM', 'phone_code' => '1809', 'currency' => 'DOP'],
            ['name' => 'Uruguay', 'code' => 'URY', 'phone_code' => '598', 'currency' => 'UYU'],
            ['name' => 'Venezuela', 'code' => 'VEN', 'phone_code' => '58', 'currency' => 'Bs'],
        ];

        foreach ($countries as $country) {
            $currencyId = Currency::query()->where('code', $country['currency'])->value('id');

            Country::query()->firstOrCreate(
                ['code' => $country['code']],
                [
                    'name' => $country['name'],
                    'phone_code' => $country['phone_code'],
                    'currency_id' => $currencyId,
                ],
            );
        }
    }
}
