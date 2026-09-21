<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MessageAuthor;
use App\Enums\MessageDirection;
use App\Enums\MessageKind;
use App\Traits\BelongsToBusiness;
use Database\Factories\ConversationMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One turn of a thread: the customer's text or the assistant's reply. */
#[Fillable(['business_id', 'conversation_id', 'direction', 'author', 'kind', 'wa_message_id', 'body', 'prompt_tokens', 'completion_tokens', 'audio_seconds', 'knowledge_sources', 'embedding'])]
class ConversationMessage extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<ConversationMessageFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => MessageDirection::class,
            'author' => MessageAuthor::class,
            'kind' => MessageKind::class,
            'knowledge_sources' => 'array',
            'embedding' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
