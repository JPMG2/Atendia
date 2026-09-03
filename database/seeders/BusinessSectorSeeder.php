<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BusinessSector;
use Illuminate\Database\Seeder;

class BusinessSectorSeeder extends Seeder
{
    /**
     * The sectors the master starts with: the broad grouping a business sees when
     * saying what it does. The concrete trade lives in {@see BusinessActivitySeeder}.
     * Demand-ordered from the 2026-09 research: retail leads nearly every LatAm
     * market and health, gastronomy, beauty and education convert the most over
     * WhatsApp. Idempotent AND the curated source: re-running realigns the rows.
     */
    public function run(): void
    {
        $sectors = [
            ['code' => 'comercio', 'name' => 'Comercio', 'description' => 'Venta de productos al público.', 'sort_order' => 1],
            ['code' => 'gastronomia', 'name' => 'Gastronomía', 'description' => 'Comida y bebida, para consumir en el local o para llevar.', 'sort_order' => 2],
            ['code' => 'salud', 'name' => 'Salud', 'description' => 'Atención de la salud y venta de medicamentos.', 'sort_order' => 3],
            ['code' => 'belleza', 'name' => 'Belleza y estética', 'description' => 'Cuidado personal y estética.', 'sort_order' => 4],
            ['code' => 'turismo', 'name' => 'Turismo y hotelería', 'description' => 'Hospedaje, viajes y experiencias.', 'sort_order' => 5],
            ['code' => 'educacion', 'name' => 'Educación', 'description' => 'Enseñanza y formación.', 'sort_order' => 6],
            ['code' => 'inmobiliaria', 'name' => 'Inmobiliaria', 'description' => 'Venta, alquiler y administración de propiedades.', 'sort_order' => 7],
            ['code' => 'servicios', 'name' => 'Servicios', 'description' => 'Oficios y servicios para el hogar o el comercio.', 'sort_order' => 8],
            ['code' => 'profesionales', 'name' => 'Servicios profesionales', 'description' => 'Estudios y consultoras que atienden con turno.', 'sort_order' => 9],
            ['code' => 'bienestar', 'name' => 'Deporte y bienestar', 'description' => 'Actividad física y entrenamiento.', 'sort_order' => 10],
            ['code' => 'mascotas', 'name' => 'Mascotas', 'description' => 'Atención y productos para animales.', 'sort_order' => 11],
            ['code' => 'automotor', 'name' => 'Automotor', 'description' => 'Mantenimiento y venta de vehículos.', 'sort_order' => 12],

            // The open door: no sector fits and the business signs up anyway.
            ['code' => 'otro', 'name' => 'Otro', 'description' => 'Ningún rubro encaja y el negocio arranca igual.', 'sort_order' => 99],
        ];

        foreach ($sectors as $sector) {
            BusinessSector::query()->updateOrCreate(
                ['code' => $sector['code']],
                $sector,
            );
        }
    }
}
