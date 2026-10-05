<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AiModel;
use App\Models\AiTask;
use Illuminate\Database\Seeder;

class AiModelSeeder extends Seeder
{
    /**
     * The models and what they cost, plus one task row per agent.
     *
     * `updateOrCreate` keyed on code + date: re-running never duplicates a
     * price, and a NEW price is a new row so the old calls keep theirs.
     */
    public function run(): void
    {
        AiModel::updateOrCreate(
            ['code' => 'gpt-6-astra', 'effective_from' => '2026-09-03'],
            [
                'provider' => 'openai',
                'label' => 'GPT-6 Astra',
                'prompt_per_million' => 10,
                'cached_per_million' => 1,
                'completion_per_million' => 50,
                'source' => 'Precio estándar publicado, verificado el 2026-10-03',
                'is_active' => true,
            ],
        );

        // One row per agent, all unassigned: an unassigned task runs on the
        // pair written in its agent, and `is_mechanical` only marks the
        // candidates — the cheap model is chosen by MEASURING.
        $tasks = [
            ['AsistenteAtendia', 'Atiende a los clientes del negocio por WhatsApp', false],
            ['AskAtendia', 'Responde las consultas de la dueña sobre su plataforma', false],
            ['ConversationAnalyst', 'Analiza de qué se trató cada conversación', false],
            ['QuestionMatcher', 'Agrupa preguntas repetidas', true],
            ['ReplyTranslator', 'Traduce una respuesta al idioma del cliente', true],
            ['ProductNameFixer', 'Corrige el nombre de un producto', true],
            ['ProductColumnMapper', 'Adivina qué columna es cuál en una importación', true],
            ['CatalogCopywriter', 'Escribe la descripción de un ítem del catálogo', true],
            ['BusinessBioWriter', 'Escribe la presentación de un negocio', true],
            ['FaqDrafter', 'Redacta preguntas frecuentes', true],
            ['DigestWriter', 'Arma el resumen del día', true],
            ['ActivityIntentDesigner', 'Diseña las intenciones de un rubro', true],
        ];

        foreach ($tasks as [$key, $label, $mechanical]) {
            AiTask::updateOrCreate(['key' => $key], ['label' => $label, 'is_mechanical' => $mechanical]);
        }
    }
}
