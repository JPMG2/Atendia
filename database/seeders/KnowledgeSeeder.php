<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Business;
use App\Models\KnowledgeDocument;
use Illuminate\Database\Seeder;

/**
 * Demo knowledge for a company. It does NOT embed here: creating a document
 * queues `IndexKnowledgeDocument` and the worker indexes it against the
 * embeddings API. Run it with the worker up and the API key set.
 */
class KnowledgeSeeder extends Seeder
{
    public function run(): void
    {
        $business = Business::query()->first() ?? Business::factory()->create();

        $faqs = [
            [
                'title' => 'Cómo conectar WhatsApp',
                'content' => 'Para conectar tu WhatsApp entrá a Configuración → Canales → WhatsApp y escaneá el código QR con el teléfono del negocio. La conexión queda activa mientras el teléfono tenga internet.',
            ],
            [
                'title' => 'Horarios de atención del asistente',
                'content' => 'El asistente responde las 24 horas. Podés definir un horario comercial para derivar a una persona del equipo fuera de ese rango desde Configuración → Atención.',
            ],
            [
                'title' => 'Precios y planes',
                'content' => 'AtendIa tiene un plan inicial con un canal y un plan pro con múltiples canales y más volumen de mensajes. La facturación es mensual y podés cambiar de plan cuando quieras.',
            ],
            [
                'title' => 'Cómo derivar a un humano',
                'content' => 'Cuando el asistente no puede resolver algo, ofrece derivar con una persona. También podés pedir "hablar con un agente" y la conversación pasa a la bandeja del equipo.',
            ],
        ];

        foreach ($faqs as $faq) {
            KnowledgeDocument::updateOrCreate(
                ['business_id' => $business->id, 'title' => $faq['title']],
                ['source_type' => 'faq', 'content' => $faq['content']],
            );
        }
    }
}
