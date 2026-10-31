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
            ['key' => 'catalog_photos', 'name' => 'Fotos del catálogo', 'description' => 'Envía por WhatsApp las fotos de un producto o servicio'],
            ['key' => 'agenda_slots', 'name' => 'Turnos libres', 'description' => 'Los huecos reales de la agenda, por día o los próximos'],
            ['key' => 'agenda_book', 'name' => 'Reservar un turno', 'description' => 'Toma el horario elegido y lo confirma'],
            ['key' => 'agenda_my_appointments', 'name' => 'Turnos del cliente', 'description' => 'Ver, cancelar o mover los turnos de quien escribe'],
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
            ['key' => 'owner_appointments', 'name' => 'Turnos del negocio', 'description' => 'Los turnos reservados en un período: quién viene, a qué hora y cómo terminó'],
            ['key' => 'owner_free_slots', 'name' => 'Horas libres', 'description' => 'Las horas que le quedan libres a la agenda, de un día o las próximas'],
            ['key' => 'owner_birthdays', 'name' => 'Cumpleaños de clientes', 'description' => 'Quién cumple años en un período y si aceptó recibir mensajes'],
            ['key' => 'owner_statistics', 'name' => 'Estadísticas', 'description' => 'Los números de la pantalla de estadísticas, a la profundidad del plan'],
            ['key' => 'owner_plan', 'name' => 'Mi plan y consumo', 'description' => 'El plan, lo que incluye y cuánto se usó este mes'],
            ['key' => 'owner_team', 'name' => 'Mi equipo', 'description' => 'Quién está disponible, qué espera en cada departamento y quién tiene más charlas'],
            ['key' => 'panel_guide', 'name' => 'Guía del panel', 'description' => 'Qué hace cada módulo y cada formulario del panel, con sus propios textos'],
        ];

        foreach ($ownerSkills as $order => $skill) {
            AssistantSkill::query()->updateOrCreate(
                ['key' => $skill['key']],
                [...$skill, 'audience' => SkillAudience::Owner, 'is_universal' => true, 'sort_order' => $order + 1],
            );
        }

        // The platform admin's own assistant: it reads across every business, so none of these ever reaches a business.
        $adminSkills = [
            ['key' => 'admin_pulse', 'name' => 'Pulso de la plataforma', 'description' => 'Negocios por situación, ingreso, comprobantes esperando, avisos y sensores'],
            ['key' => 'admin_find_business', 'name' => 'Buscar un negocio', 'description' => 'Encuentra negocios por nombre o situación, con su plan y su enlace'],
            ['key' => 'admin_business_file', 'name' => 'Ficha de un negocio', 'description' => 'Plan, cobros, uso del mes, WhatsApp y avisos de un negocio, sin lo que escriben sus clientes'],
            ['key' => 'admin_alerts', 'name' => 'Avisos del tablero', 'description' => 'Los avisos abiertos, los más graves primero, de la plataforma o de un negocio'],
            ['key' => 'admin_payment_queue', 'name' => 'Comprobantes por verificar', 'description' => 'Los pagos que esperan su verificación, el más viejo primero'],
            ['key' => 'admin_reports', 'name' => 'Reportes', 'description' => 'El enlace en PDF, Excel o CSV a un reporte del panel de administración'],
            ['key' => 'admin_email_business', 'name' => 'Correo a un negocio', 'description' => 'Redacta un correo del equipo a un negocio; sale solo si ella lo aprueba'],
        ];

        foreach ($adminSkills as $order => $skill) {
            AssistantSkill::query()->updateOrCreate(
                ['key' => $skill['key']],
                [...$skill, 'audience' => SkillAudience::Admin, 'is_universal' => true, 'sort_order' => $order + 1],
            );
        }
    }
}
