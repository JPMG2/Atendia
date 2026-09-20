<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\Ai\Agents\ReplyTranslator;
use App\Enums\ConversationStatus;
use App\Enums\MessageAuthor;
use App\Enums\MessageDirection;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Services\EvolutionApi;

/**
 * A human answers from the panel: the text rides the business's own
 * WhatsApp, lands in the thread as a HUMAN turn (the assistant reads it
 * back as memory), and the ball passes to the customer.
 */
class SendHumanReply
{
    public function __construct(private EvolutionApi $evolution) {}

    public function handle(Business $business, Conversation $conversation, string $text): ?ConversationMessage
    {
        if (! $business->isConnected() || $business->whatsapp_instance === null) {
            return null;
        }

        $text = $this->inCustomerLanguage($conversation, $text);

        $this->evolution->sendText($business->whatsapp_instance, $conversation->contact_phone, $text);

        $message = $conversation->messages()->create([
            'direction' => MessageDirection::Out,
            'author' => MessageAuthor::Human,
            'body' => $text,
        ]);

        // The ball passes to the customer, and the forgotten-thread clock stops.
        $conversation->fill([
            'status' => ConversationStatus::Customer,
            'escalated_at' => null,
            'handoff_reminded_at' => null,
            'last_message_at' => now(),
        ])->save();

        return $message;
    }

    /**
     * The team always writes in Spanish; the customer always reads their
     * own language. The thread records what was actually SENT — and a
     * failed translation falls back to the Spanish original, never to
     * silence.
     */
    private function inCustomerLanguage(Conversation $conversation, string $text): string
    {
        $language = strtolower((string) $conversation->language);

        if ($language === '' || str_starts_with($language, 'es')) {
            return $text;
        }

        return rescue(function () use ($language, $text): string {
            $response = new ReplyTranslator()->prompt(
                "Idioma del cliente: {$language}\n\nRespuesta del equipo (en español):\n{$text}",
            );

            $translated = trim((string) ($response['text'] ?? ''));

            return $translated !== '' ? $translated : $text;
        }, $text, report: false);
    }
}
