<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\MentionGoal;
use Illuminate\Database\Seeder;

class MentionGoalSeeder extends Seeder
{
    /**
     * The first goals, so the offer exists on day one. She rewrites or adds
     * any of them from the Catalogs hub: this only creates what is missing.
     */
    public function run(): void
    {
        foreach ([100, 500, 1000] as $threshold) {
            MentionGoal::query()->firstOrCreate(
                ['threshold' => $threshold],
                ['caption' => '{negocio} ya atendió {logro} con su asistente. ¡Felicitaciones!', 'is_active' => true],
            );
        }
    }
}
