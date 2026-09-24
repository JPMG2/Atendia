<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AssistantSkill;
use Illuminate\Database\Seeder;

class AssistantSkillSeeder extends Seeder
{
    /**
     * The skills every business gets. Keyed by `key` so a re-run refreshes
     * the wording without touching which trades use what.
     */
    public function run(): void
    {
        $skills = [
            ['key' => 'catalog', 'name' => 'Catálogo', 'description' => 'Servicios y productos con precio, duración, preparación y stock'],
            ['key' => 'hours', 'name' => 'Horarios', 'description' => 'Los horarios de la semana y si está abierto ahora'],
            ['key' => 'contact', 'name' => 'Ubicación y contacto', 'description' => 'Dirección, si tiene local, WhatsApp, correo, web y redes'],
            ['key' => 'knowledge', 'name' => 'Base de conocimiento', 'description' => 'Respuestas enseñadas y documentos: la red para lo demás'],
            ['key' => 'escalate', 'name' => 'Derivar a una persona', 'description' => 'Pasa la charla al equipo'],
            ['key' => 'remember_customer', 'name' => 'Recordar datos del cliente', 'description' => 'Guarda el nombre, el cumpleaños y otros datos que da el cliente'],
        ];

        foreach ($skills as $order => $skill) {
            AssistantSkill::query()->updateOrCreate(
                ['key' => $skill['key']],
                [...$skill, 'is_universal' => true, 'sort_order' => $order + 1],
            );
        }
    }
}
