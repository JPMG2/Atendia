<?php

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
        $statuses = [
            ['name' => 'Activo'],
            ['name' => 'Bloqueado'],
            ['name' => 'Pendiente'],
            ['name' => 'Rechazado'],
            ['name' => 'Suspendido'],
            ['name' => 'Eliminado'],
            ['name' => 'Pausado'],
            ['name' => 'En proceso'],
            ['name' => 'Finalizado'],
        ];

        foreach ($statuses as $status) {
            CurrentStatus::query()->firstOrCreate($status);
        }
    }
}
