<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QuestionResolution;
use App\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer's question rewritten to stand on its own: the semantic unit
 * that topics, suggestions and statistics group and count.
 */
#[Fillable(['business_id', 'conversation_id', 'conversation_analysis_id', 'conversation_message_id', 'question_intent_id', 'question', 'subject', 'service_id', 'product_id', 'resolved_by', 'answer', 'customer_notified_at', 'knowledge_suggestion_id', 'embedding'])]
class ConversationQuestion extends Model
{
    use BelongsToBusiness;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'resolved_by' => QuestionResolution::class,
            'embedding' => 'array',
            'customer_notified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<QuestionIntent, $this>
     */
    public function intent(): BelongsTo
    {
        return $this->belongsTo(QuestionIntent::class, 'question_intent_id');
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * @return BelongsTo<KnowledgeSuggestion, $this>
     */
    public function suggestion(): BelongsTo
    {
        return $this->belongsTo(KnowledgeSuggestion::class, 'knowledge_suggestion_id');
    }

    /**
     * @return BelongsTo<ConversationMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(ConversationMessage::class, 'conversation_message_id');
    }
}
