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
        // The colour is a semantic token KEY (see CurrentStatus::COLORS): green
        // for fine, amber for waiting, red for stopped, grey for no longer
        // counting.
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

        // `updateOrCreate` and not `firstOrCreate`: the nine statuses already
        // existed without a colour, and firstOrCreate would have left them grey.
        foreach ($statuses as $status) {
            CurrentStatus::query()->updateOrCreate(
                ['name' => $status['name']],
                ['color' => $status['color']],
            );
        }
    }
}
