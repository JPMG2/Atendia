<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BusinessSector;
use Illuminate\Database\Seeder;

class BusinessSectorSeeder extends Seeder
{
    /**
     * Rubros con los que arranca el maestro. Es la agrupación mayor que ve el
     * negocio al elegir a qué se dedica; el oficio concreto vive en
     * {@see BusinessActivitySeeder}.
     *
     * Idempotente: keyed por `code`, que es la clave estable del rubro.
     */
    public function run(): void
    {
        $sectors = [
            ['code' => 'gastronomia', 'name' => 'Gastronomía', 'description' => 'Comida y bebida, para consumir en el local o para llevar.', 'sort_order' => 1],
            ['code' => 'salud', 'name' => 'Salud', 'description' => 'Atención de la salud y venta de medicamentos.', 'sort_order' => 2],
            ['code' => 'belleza', 'name' => 'Belleza y estética', 'description' => 'Cuidado personal y estética.', 'sort_order' => 3],
            ['code' => 'comercio', 'name' => 'Comercio', 'description' => 'Venta de productos al público.', 'sort_order' => 4],
            ['code' => 'servicios', 'name' => 'Servicios', 'description' => 'Oficios y servicios para el hogar o el comercio.', 'sort_order' => 5],
            ['code' => 'profesionales', 'name' => 'Servicios profesionales', 'description' => 'Estudios y consultoras que atienden con turno.', 'sort_order' => 6],
            ['code' => 'bienestar', 'name' => 'Deporte y bienestar', 'description' => 'Actividad física y entrenamiento.', 'sort_order' => 7],
            ['code' => 'mascotas', 'name' => 'Mascotas', 'description' => 'Atención y productos para animales.', 'sort_order' => 8],
            ['code' => 'automotor', 'name' => 'Automotor', 'description' => 'Mantenimiento y venta de vehículos.', 'sort_order' => 9],
            ['code' => 'educacion', 'name' => 'Educación', 'description' => 'Enseñanza y formación.', 'sort_order' => 10],
        ];

        foreach ($sectors as $sector) {
            BusinessSector::query()->firstOrCreate(
                ['code' => $sector['code']],
                $sector,
            );
        }
    }
}
