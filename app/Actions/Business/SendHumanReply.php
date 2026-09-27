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
    public function __construct(private EvolutionApi $evolution, private TranslateForCustomer $translate) {}

    public function handle(Business $business, Conversation $conversation, string $text): ?ConversationMessage
    {
        if (! $business->canMessageCustomers()) {
            return null;
        }

        $text = $this->translate->handle($conversation, $text);

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
}
