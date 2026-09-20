<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Enums\ConversationStatus;
use App\Models\Business;
use App\Models\Conversation;
use App\Services\EvolutionApi;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The assistant's raised hand: THIS thread goes to the team, the owner is
 * pinged on their own WhatsApp with the reason in Spanish, and the
 * assistant falls silent in this thread only. Pinned at construction —
 * the model never chooses which thread it escalates.
 */
class EscalateToHuman implements Tool
{
    public function __construct(
        private readonly Business $business,
        private readonly Conversation $conversation,
    ) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Deriva ESTA conversación a una persona del equipo del negocio. Usala '
            .'solo cuando tus instrucciones de derivación lo indiquen. Recibe "reason": '
            .'el motivo en UNA frase, SIEMPRE en español.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $reason = trim((string) $request['reason']);

        $this->conversation->update([
            'status' => ConversationStatus::Team,
            'escalated_at' => now(),
            'handoff_reminded_at' => null,
        ]);

        // Best effort: a failed ping must never break the escalation itself.
        rescue(fn () => $this->alertOwner($reason), report: false);

        return 'Equipo avisado. Ahora despedite con cortesía en el idioma del cliente: '
            .'una persona del equipo le escribe a la brevedad. No sigas resolviendo esta consulta.';
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'reason' => $schema->string()->required(),
        ];
    }

    private function alertOwner(string $reason): void
    {
        $number = (string) preg_replace('/\D/', '', (string) $this->business->fallback_whatsapp_number);

        if ($number === '' || $this->business->whatsapp_instance === null) {
            return;
        }

        app(EvolutionApi::class)->sendText(
            $this->business->whatsapp_instance,
            $number,
            __('assistant.handoff.owner_alert', [
                'name' => $this->conversation->contact_name ?? $this->conversation->contact_phone,
                'phone' => $this->conversation->contact_phone,
                'reason' => $reason !== '' ? $reason : __('assistant.handoff.no_reason'),
            ]),
        );
    }
}
