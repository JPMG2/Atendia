<?php

declare(strict_types=1);

namespace App\Actions\Business;

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

        $this->evolution->sendText($business->whatsapp_instance, $conversation->contact_phone, $text);

        $message = $conversation->messages()->create([
            'direction' => MessageDirection::Out,
            'author' => MessageAuthor::Human,
            'body' => $text,
        ]);

        $conversation->fill([
            'status' => ConversationStatus::Customer,
            'last_message_at' => now(),
        ])->save();

        return $message;
    }
}
