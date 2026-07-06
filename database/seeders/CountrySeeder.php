<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $countries = [
            ['name' => 'Argentina', 'code' => 'ARG', 'phone_code' => '54'],
            ['name' => 'Bolivia', 'code' => 'BOL', 'phone_code' => '591'],
            ['name' => 'Brasil', 'code' => 'BRA', 'phone_code' => '55'],
            ['name' => 'Canadá', 'code' => 'CAN', 'phone_code' => '1'],
            ['name' => 'Chile', 'code' => 'CHL', 'phone_code' => '56'],
            ['name' => 'Colombia', 'code' => 'COL', 'phone_code' => '57'],
            ['name' => 'Costa Rica', 'code' => 'CRI', 'phone_code' => '506'],
            ['name' => 'Cuba', 'code' => 'CUB', 'phone_code' => '53'],
            ['name' => 'Ecuador', 'code' => 'ECU', 'phone_code' => '593'],
            ['name' => 'El Salvador', 'code' => 'SLV', 'phone_code' => '503'],
            ['name' => 'Estados Unidos', 'code' => 'USA', 'phone_code' => '1'],
            ['name' => 'Guatemala', 'code' => 'GTM', 'phone_code' => '502'],
            ['name' => 'Honduras', 'code' => 'HND', 'phone_code' => '504'],
            ['name' => 'México', 'code' => 'MEX', 'phone_code' => '52'],
            ['name' => 'Nicaragua', 'code' => 'NIC', 'phone_code' => '505'],
            ['name' => 'Panamá', 'code' => 'PAN', 'phone_code' => '507'],
            ['name' => 'Paraguay', 'code' => 'PRY', 'phone_code' => '595'],
            ['name' => 'Perú', 'code' => 'PER', 'phone_code' => '51'],
            ['name' => 'Puerto Rico', 'code' => 'PRI', 'phone_code' => '1787'],
            ['name' => 'República Dominicana', 'code' => 'DOM', 'phone_code' => '1809'],
            ['name' => 'Uruguay', 'code' => 'URY', 'phone_code' => '598'],
            ['name' => 'Venezuela', 'code' => 'VEN', 'phone_code' => '58'],
        ];

        foreach ($countries as $country) {
            Country::query()->firstOrCreate($country);
        }
    }
}
