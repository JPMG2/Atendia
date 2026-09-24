<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QuestionResolution;
use App\Enums\SuggestionStatus;
use App\Traits\BelongsToBusiness;
use Database\Factories\KnowledgeSuggestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One distinct question the assistant could not answer, with every time it
 * was asked linked to it. Replaces the two old queues (knowledge misses and
 * the per-message "needs teaching" flag) with a single one.
 */
#[Fillable(['business_id', 'question_intent_id', 'knowledge_document_id', 'question', 'status', 'embedding', 'status_changed_at'])]
class KnowledgeSuggestion extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<KnowledgeSuggestionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SuggestionStatus::class,
            'embedding' => 'array',
            'status_changed_at' => 'datetime',
        ];
    }

    /**
     * The current tenant's suggestions nearest in meaning, best first. Every
     * status on purpose: a dismissed one must keep absorbing its repeats.
     *
     * @param  list<float>  $vector
     * @return list<array{id: int, question: string, similarity: float}>
     */
    public static function closestMany(array $vector, int $limit): array
    {
        return self::query()
            ->whereNotNull('embedding')
            ->select(['id', 'question'])
            ->selectVectorDistance('embedding', $vector, as: 'distance')
            ->orderByVectorDistance('embedding', $vector)
            ->limit($limit)
            ->get()
            ->map(fn (self $suggestion): array => [
                'id' => (int) $suggestion->id,
                'question' => $suggestion->question,
                'similarity' => 1 - (float) $suggestion->getAttribute('distance'),
            ])
            ->all();
    }

    /**
     * The owner's queue: pending items with how often and how lately they
     * were asked, and the team's latest answer as the draft. Most asked first.
     *
     * @param  Builder<KnowledgeSuggestion>  $query
     */
    public function scopeQueue(Builder $query): void
    {
        $query->where('status', SuggestionStatus::Pending)
            ->with(['intent:id,name', 'teamAnswer', 'latestQuestion'])
            ->withCount('questions as asked_count')
            ->withMax('questions as last_asked_at', 'created_at')
            ->orderByDesc('asked_count')
            ->orderByDesc('last_asked_at');
    }

    /**
     * Which messages of one thread asked a question still in the queue:
     * where the thread offers to teach it.
     *
     * @return array<int, int> message id => suggestion id
     */
    public static function pendingByMessage(int $conversationId): array
    {
        return ConversationQuestion::query()
            ->where('conversation_id', $conversationId)
            ->whereNotNull('conversation_message_id')
            ->whereHas('suggestion', fn (Builder $suggestion): Builder => $suggestion->where('status', SuggestionStatus::Pending))
            ->pluck('knowledge_suggestion_id', 'conversation_message_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    /** A new failure after teaching means the lesson did not stick: back to the queue. */
    public function absorbFailure(): void
    {
        if ($this->status === SuggestionStatus::Taught) {
            $this->forceFill(['status' => SuggestionStatus::Pending, 'status_changed_at' => now()])->save();
        }
    }

    public function markTaught(KnowledgeDocument $document): void
    {
        $this->forceFill([
            'status' => SuggestionStatus::Taught,
            'knowledge_document_id' => $document->id,
            'status_changed_at' => now(),
        ])->save();
    }

    public function dismiss(): void
    {
        $this->forceFill(['status' => SuggestionStatus::Dismissed, 'status_changed_at' => now()])->save();
    }

    /**
     * @return HasMany<ConversationQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(ConversationQuestion::class);
    }

    /**
     * The freshest answer the team gave to this question: the draft.
     *
     * @return HasOne<ConversationQuestion, $this>
     */
    public function teamAnswer(): HasOne
    {
        // The filter goes INSIDE ofMany: outside it, a later unanswered asking
        // wins the max(id) and the draft vanishes.
        return $this->hasOne(ConversationQuestion::class)->ofMany(
            ['id' => 'max'],
            fn (Builder $answered): Builder => $answered->where('resolved_by', QuestionResolution::Team)->whereNotNull('answer'),
        );
    }

    /**
     * Askings left without any answer, recent enough to still be worth a
     * message, in threads that went quiet: a live chat is not interrupted.
     * Seven days: past that the customer has solved it elsewhere.
     *
     * @return HasMany<ConversationQuestion, $this>
     */
    public function notifiableQuestions(): HasMany
    {
        $quietSince = now()->subHours((int) config('atendia.analysis.idle_hours'));

        return $this->questions()
            ->where('resolved_by', QuestionResolution::Nobody)
            ->whereNull('customer_notified_at')
            ->where('created_at', '>=', now()->subDays(7))
            ->whereHas('conversation', fn (Builder $thread): Builder => $thread->where('last_message_at', '<', $quietSince));
    }

    /** How many distinct customers would get the taught answer. */
    public function notifiableCustomers(): int
    {
        return $this->notifiableQuestions()->distinct()->count('conversation_id');
    }

    /**
     * The freshest asking: the thread to read before teaching.
     *
     * @return HasOne<ConversationQuestion, $this>
     */
    public function latestQuestion(): HasOne
    {
        return $this->hasOne(ConversationQuestion::class)->latestOfMany();
    }

    /**
     * @return BelongsTo<QuestionIntent, $this>
     */
    public function intent(): BelongsTo
    {
        return $this->belongsTo(QuestionIntent::class, 'question_intent_id');
    }

    /**
     * @return BelongsTo<KnowledgeDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(KnowledgeDocument::class, 'knowledge_document_id');
    }
}
