<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AiCapability;
use App\Models\AiConnection;
use App\Models\AiModel;
use App\Models\AiTask;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AiModelSeeder extends Seeder
{
    /** The names of the keys the platform actually has; any other entry is named after its key. */
    private const array CONNECTION_LABELS = [
        'openai' => 'OpenAI · principal',
        'openai-app' => 'OpenAI · IA del cliente',
    ];

    /**
     * Prices are `updateOrCreate` on code + date: a NEW price is a new row, so
     * old calls keep theirs. Connections are `firstOrCreate`: the admin may
     * rename one and re-seeding must not undo it.
     */
    public function run(): void
    {
        foreach (array_keys((array) config('ai.providers')) as $key) {
            AiConnection::firstOrCreate(
                ['key' => $key],
                ['label' => self::CONNECTION_LABELS[$key] ?? Str::headline($key), 'is_active' => true],
            );
        }

        AiModel::updateOrCreate(
            ['code' => 'gpt-6-astra', 'effective_from' => '2026-09-03'],
            [
                'provider' => 'openai',
                'capability' => AiCapability::Vision,
                'label' => 'GPT-6 Astra',
                'prompt_per_million' => 10,
                'cached_per_million' => 1,
                'completion_per_million' => 50,
                'source' => 'Precio estándar publicado, verificado el 2026-10-03',
                'is_active' => true,
            ],
        );

        // The models the agents run on and the ones measured against them. They were
        // only ever rows in `atendia`: a server born from the seeder had none of them,
        // and the agents' declared default (Luna) had no price to be charged at.
        foreach ([
            ['gpt-6-sol', 'GPT-6 Sol', AiCapability::Vision, 2, 0.2, 10, null],
            ['gpt-6.1-sol', 'GPT-6.1 Sol', AiCapability::Vision, 2, 0.1, 10, null],
            ['gpt-6-luna', 'GPT-6 Luna', AiCapability::Vision, 0.1, 0.01, 0.5, null],
            ['gpt-4o-mini-transcribe', 'GPT-4o mini Transcribe', AiCapability::Transcription, 0, 0, 0, 0.003],
            ['gpt-4o-transcribe', 'GPT-4o Transcribe', AiCapability::Transcription, 0, 0, 0, 0.006],
            ['gpt-transcribe', 'GPT Transcribe', AiCapability::Transcription, 0, 0, 0, 0.0045],
        ] as [$code, $label, $capability, $prompt, $cached, $completion, $perMinute]) {
            AiModel::updateOrCreate(
                ['code' => $code, 'effective_from' => '2026-09-03'],
                [
                    'provider' => 'openai',
                    'capability' => $capability,
                    'label' => $label,
                    'prompt_per_million' => $prompt,
                    'cached_per_million' => $cached,
                    'completion_per_million' => $completion,
                    'per_minute' => $perMinute,
                    'source' => 'Precio estándar de developers.openai.com/api/docs/pricing, verificado el 2026-10-08',
                    'is_active' => true,
                ],
            );
        }

        // The models the knowledge search could move to. The vector length is part
        // of what each one is: 3-large natively returns 3072, past what pgvector
        // can index, so it is listed at the 1536 the OpenAI parameter cuts it to.
        foreach ([
            ['text-embedding-3-small', 'Embedding 3 small', 1536, 0.02],
            ['text-embedding-3-large', 'Embedding 3 large', 1536, 0.13],
        ] as [$code, $label, $dimensions, $price]) {
            AiModel::updateOrCreate(
                ['code' => $code, 'effective_from' => '2026-10-08'],
                [
                    'provider' => 'openai',
                    'capability' => AiCapability::Embedding,
                    'label' => $label,
                    'prompt_per_million' => $price,
                    'dimensions' => $dimensions,
                    'source' => 'Precio estándar publicado, verificado el 2026-10-08',
                    'is_active' => true,
                ],
            );
        }

        // Unassigned rows run on the agent's own pair; `is_mechanical` only marks
        // cheap-model candidates, chosen by MEASURING. The assistant reads the
        // photos customers send, so it needs a model that can see.
        $tasks = [
            ['AsistenteAtendia', 'Atiende a los clientes del negocio por WhatsApp', false, AiCapability::Vision],
            ['AskAtendia', 'Responde las consultas de la dueña sobre su plataforma', false, AiCapability::Text],
            ['ConversationAnalyst', 'Analiza de qué se trató cada conversación', false, AiCapability::Text],
            ['QuestionMatcher', 'Agrupa preguntas repetidas', true, AiCapability::Text],
            ['ReplyTranslator', 'Traduce una respuesta al idioma del cliente', true, AiCapability::Text],
            ['ProductNameFixer', 'Corrige el nombre de un producto', true, AiCapability::Text],
            ['ProductColumnMapper', 'Adivina qué columna es cuál en una importación', true, AiCapability::Text],
            ['CatalogCopywriter', 'Escribe la descripción de un ítem del catálogo', true, AiCapability::Text],
            ['BusinessBioWriter', 'Escribe la presentación de un negocio', true, AiCapability::Text],
            ['FaqDrafter', 'Redacta preguntas frecuentes', true, AiCapability::Text],
            ['DigestWriter', 'Arma el resumen del día', true, AiCapability::Text],
            ['ActivityIntentDesigner', 'Diseña las intenciones de un rubro', true, AiCapability::Text],
            [AiTask::TRANSCRIPTION, 'Transcribe el audio que manda el cliente', true, AiCapability::Transcription],
        ];

        foreach ($tasks as [$key, $label, $mechanical, $capability]) {
            AiTask::updateOrCreate(['key' => $key], ['label' => $label, 'is_mechanical' => $mechanical, 'capability' => $capability]);
        }
    }
}
