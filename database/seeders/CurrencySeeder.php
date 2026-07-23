<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $currencies = [
            ['code' => 'ARS', 'name' => 'Peso Argentino', 'symbol' => '$', 'decimal_places' => 2],
            ['code' => 'Bs', 'name' => 'Bolívar', 'symbol' => 'Bs', 'decimal_places' => 2],
            ['code' => 'USD', 'name' => 'Dólar Estadounidense', 'symbol' => '$', 'decimal_places' => 2],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'decimal_places' => 2],
            ['code' => 'BOB', 'name' => 'Boliviano', 'symbol' => 'Bs', 'decimal_places' => 2],
            ['code' => 'BRL', 'name' => 'Real Brasileño', 'symbol' => 'R$', 'decimal_places' => 2],
            ['code' => 'CAD', 'name' => 'Dólar Canadiense', 'symbol' => '$', 'decimal_places' => 2],
            ['code' => 'CLP', 'name' => 'Peso Chileno', 'symbol' => '$', 'decimal_places' => 0],
            ['code' => 'COP', 'name' => 'Peso Colombiano', 'symbol' => '$', 'decimal_places' => 2],
            ['code' => 'CRC', 'name' => 'Colón Costarricense', 'symbol' => '₡', 'decimal_places' => 2],
            ['code' => 'CUP', 'name' => 'Peso Cubano', 'symbol' => '$', 'decimal_places' => 2],
            ['code' => 'GTQ', 'name' => 'Quetzal', 'symbol' => 'Q', 'decimal_places' => 2],
            ['code' => 'HNL', 'name' => 'Lempira', 'symbol' => 'L', 'decimal_places' => 2],
            ['code' => 'MXN', 'name' => 'Peso Mexicano', 'symbol' => '$', 'decimal_places' => 2],
            ['code' => 'NIO', 'name' => 'Córdoba', 'symbol' => 'C$', 'decimal_places' => 2],
            ['code' => 'PAB', 'name' => 'Balboa', 'symbol' => 'B/.', 'decimal_places' => 2],
            ['code' => 'DOP', 'name' => 'Peso Dominicano', 'symbol' => 'RD$', 'decimal_places' => 2],
            ['code' => 'UYU', 'name' => 'Peso Uruguayo', 'symbol' => '$U', 'decimal_places' => 2],
            ['code' => 'PYG', 'name' => 'Guaraní', 'symbol' => '₲', 'decimal_places' => 0],
            ['code' => 'PEN', 'name' => 'Sol', 'symbol' => 'S/', 'decimal_places' => 2],
        ];

        foreach ($currencies as $currency) {
            Currency::query()->firstOrCreate(
                ['code' => $currency['code']],
                $currency,
            );
        }
    }
}
