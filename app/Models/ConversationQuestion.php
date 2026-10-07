<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QuestionResolution;
use App\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer's question rewritten to stand on its own: the semantic unit
 * that topics, suggestions and statistics group and count.
 */
#[Fillable(['business_id', 'conversation_id', 'conversation_analysis_id', 'conversation_message_id', 'question_intent_id', 'question', 'subject', 'service_id', 'product_id', 'resolved_by', 'asked_at', 'answer', 'customer_notified_at', 'knowledge_suggestion_id', 'embedding'])]
class ConversationQuestion extends Model
{
    use BelongsToBusiness;

    protected static function booted(): void
    {
        // The analysis always stamps the asking message's time; a row written
        // by hand without one is treated as asked now.
        static::creating(function (ConversationQuestion $question): void {
            $question->asked_at ??= now();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'resolved_by' => QuestionResolution::class,
            'embedding' => 'array',
            'customer_notified_at' => 'datetime',
            'asked_at' => 'datetime',
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

    /**
     * Threads where the customer asked more than once and nobody ever
     * answered: insisting is what somebody does before giving up, and it is
     * the only one of these signals the customer sends on purpose.
     *
     * One row per thread, carrying the LAST question — the words they used
     * when they were already tired of asking.
     *
     * @return EloquentCollection<int, static>
     */
    public static function repeatedUnanswered(int $sinceDays): EloquentCollection
    {
        $threads = static::query()
            ->selectRaw('conversation_id')
            ->where('resolved_by', QuestionResolution::Nobody)
            ->where('created_at', '>=', now()->subDays($sinceDays))
            ->groupBy('conversation_id')
            ->havingRaw('count(*) >= 2');

        return static::query()
            ->whereIn('conversation_id', $threads)
            ->where('resolved_by', QuestionResolution::Nobody)
            ->whereNotIn('business_id', Business::demoIds())
            ->with(['business:id,name', 'conversation:id,contact_name,contact_phone'])
            ->latest('id')
            ->get()
            ->unique('conversation_id')
            ->values();
    }
}
