<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\SkillAudience;
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
                [...$skill, 'audience' => SkillAudience::Customer, 'is_universal' => true, 'sort_order' => $order + 1],
            );
        }

        // "Ask AtendIa": the owner's own questions, read-only, never handed to the WhatsApp assistant.
        $ownerSkills = [
            ['key' => 'owner_conversations', 'name' => 'Conversaciones del período', 'description' => 'Cuántas conversaciones hubo, cuáles esperan al equipo y el enlace a cada una'],
            ['key' => 'owner_birthdays', 'name' => 'Cumpleaños de clientes', 'description' => 'Quién cumple años en un período y si aceptó recibir mensajes'],
            ['key' => 'owner_statistics', 'name' => 'Mis estadísticas', 'description' => 'Los números de la pantalla de estadísticas, a la profundidad del plan'],
            ['key' => 'owner_plan', 'name' => 'Mi plan y consumo', 'description' => 'El plan, lo que incluye y cuánto se usó este mes'],
            ['key' => 'panel_guide', 'name' => 'Guía del panel', 'description' => 'Qué hace cada módulo y cada formulario del panel, con sus propios textos'],
        ];

        foreach ($ownerSkills as $order => $skill) {
            AssistantSkill::query()->updateOrCreate(
                ['key' => $skill['key']],
                [...$skill, 'audience' => SkillAudience::Owner, 'is_universal' => true, 'sort_order' => $order + 1],
            );
        }
    }
}
