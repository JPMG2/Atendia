<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\Enums\MessageAuthor;
use App\Enums\MessageDirection;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Services\EvolutionApi;

/**
 * A message the ASSISTANT sends on its own, like a late answer: it lands as
 * an assistant turn and leaves the thread's status alone, so the assistant
 * keeps answering when the customer writes back.
 */
class SendAssistantNotice
{
    public function __construct(private EvolutionApi $evolution, private TranslateForCustomer $translate) {}

    public function handle(Business $business, Conversation $conversation, string $text): ?ConversationMessage
    {
        if (! $business->isConnected() || $business->whatsapp_instance === null) {
            return null;
        }

        $text = $this->translate->handle($conversation, $text);

        $this->evolution->sendText($business->whatsapp_instance, $conversation->contact_phone, $text);

        $message = $conversation->messages()->create([
            'direction' => MessageDirection::Out,
            'author' => MessageAuthor::Assistant,
            'body' => $text,
        ]);

        $conversation->forceFill(['last_message_at' => now()])->save();

        return $message;
    }
}
