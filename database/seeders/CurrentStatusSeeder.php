<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CurrentStatus;
use Illuminate\Database\Seeder;

class CurrentStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // El color es la CLAVE de un token semántico (ver CurrentStatus::COLORS):
        // verde lo que está bien, ámbar lo que espera, rojo lo que se frenó,
        // gris lo que ya no cuenta.
        $statuses = [
            ['name' => 'Activo', 'color' => 'success'],
            ['name' => 'Finalizado', 'color' => 'success'],
            ['name' => 'En proceso', 'color' => 'info'],
            ['name' => 'Pendiente', 'color' => 'warning'],
            ['name' => 'Pausado', 'color' => 'warning'],
            ['name' => 'Suspendido', 'color' => 'warning'],
            ['name' => 'Bloqueado', 'color' => 'danger'],
            ['name' => 'Rechazado', 'color' => 'danger'],
            ['name' => 'Eliminado', 'color' => 'neutral'],
        ];

        // `updateOrCreate` y no `firstOrCreate`: los 9 estados ya existían sin
        // color, así que un firstOrCreate los habría dejado a todos en gris.
        foreach ($statuses as $status) {
            CurrentStatus::query()->updateOrCreate(
                ['name' => $status['name']],
                ['color' => $status['color']],
            );
        }
    }
}
