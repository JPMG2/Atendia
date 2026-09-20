<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\Enums\MessageAuthor;
use App\Enums\MessageDirection;
use App\Enums\MessageKind;
use App\Models\Conversation;
use App\Models\ConversationMessage;

/**
 * The owner's private margin inside a thread. It never rides WhatsApp,
 * never reaches the assistant's memory, and never reorders the inbox.
 */
class SaveInternalNote
{
    public function handle(Conversation $conversation, string $text): ConversationMessage
    {
        return $conversation->messages()->create([
            'direction' => MessageDirection::Out,
            'author' => MessageAuthor::Human,
            'kind' => MessageKind::Note,
            'body' => $text,
        ]);
    }
}
