<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConversationStatus;
use App\Enums\MessageDirection;
use App\Enums\MessageKind;
use App\Services\Tenant;
use App\Traits\BelongsToBusiness;
use App\Traits\SearchesText;
use Carbon\CarbonInterface;
use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One WhatsApp thread between a business and one customer. The reply
 * worker writes it; the assistant reads it back as memory.
 */
#[Fillable(['business_id', 'customer_id', 'department_id', 'assigned_user_id', 'contact_phone', 'contact_name', 'language', 'status', 'escalated_at', 'handoff_reminded_at', 'last_message_at'])]
class Conversation extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<ConversationFactory> */
    use HasFactory;

    use SearchesText;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ConversationStatus::class,
            'escalated_at' => 'datetime',
            'handoff_reminded_at' => 'datetime',
            'last_message_at' => 'datetime',
            'analyzed_message_id' => 'integer',
        ];
    }

    /**
     * Landing-facing tally: distinct threads answered platform-wide in the
     * last 7 days. Public content, so it escapes the tenant scope on
     * purpose (Tenant::for(null), the Testimonial::published() pattern);
     * demo businesses never inflate it.
     */
    public static function answeredThisWeekCount(): int
    {
        return app(Tenant::class)->for(null, fn (): int => ConversationMessage::query()
            ->where('direction', MessageDirection::Out)
            ->where('created_at', '>=', now()->subDays(7))
            ->whereNotIn('business_id', Business::query()
                ->whereIn('billing_email', Business::DEMO_EMAILS)
                ->select('id'))
            ->distinct('conversation_id')
            ->count('conversation_id'));
    }

    /**
     * Threads with an unanalyzed stretch that has finished: resolved, or
     * quiet for the idle window. A thread waiting for the team is still
     * going — its questions are not settled yet.
     *
     * @param  Builder<Conversation>  $query
     */
    public function scopeReadyForAnalysis(Builder $query, int $idleHours, ?int $withinDays = null): void
    {
        $query->where('status', '!=', ConversationStatus::Team)
            ->where(fn (Builder $finished): Builder => $finished
                ->where('status', ConversationStatus::Resolved)
                ->orWhere('last_message_at', '<=', now()->subHours($idleHours)))
            ->whereExists(fn ($pending) => $pending->selectRaw('1')
                ->from('conversation_messages')
                ->whereColumn('conversation_messages.conversation_id', 'conversations.id')
                ->where('conversation_messages.kind', MessageKind::Message->value)
                ->whereRaw('conversation_messages.id > coalesce(conversations.analyzed_message_id, 0)'))
            ->when($withinDays !== null, fn (Builder $recent): Builder => $recent->where('last_message_at', '>=', now()->subDays($withinDays)));
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * What an agent may open: threads routed to one of their departments or
     * taken by them. An agent in no department sees the whole inbox (the
     * plans without departments); the owner is never narrowed.
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->isAgent()) {
            return;
        }

        $departmentIds = $user->departments()->pluck('departments.id');

        if ($departmentIds->isEmpty()) {
            return;
        }

        $query->where(fn (Builder $visible) => $visible
            ->whereIn('department_id', $departmentIds)
            ->orWhere('assigned_user_id', $user->id));
    }

    public function isVisibleTo(User $user): bool
    {
        return self::query()->whereKey($this->getKey())->visibleTo($user)->exists();
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<ConversationMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class);
    }

    /**
     * Everything the customer sent besides words, newest first and split by
     * kind: the whole thread, not just the loaded window of messages.
     *
     * @return array{image: list<array{message_id: int, index: int, item: array<string, mixed>, sent_at: ?CarbonInterface}>, document: list<array{message_id: int, index: int, item: array<string, mixed>, sent_at: ?CarbonInterface}>, location: list<array{message_id: int, index: int, item: array<string, mixed>, sent_at: ?CarbonInterface}>}
     */
    public function attachments(): array
    {
        $files = ['image' => [], 'document' => [], 'location' => []];

        $this->messages()
            ->whereNotNull('media')
            ->latest('id')
            ->get(['id', 'media', 'created_at'])
            ->each(function (ConversationMessage $message) use (&$files): void {
                foreach ($message->media ?? [] as $index => $item) {
                    $files[$item['kind'] ?? 'document'][] = [
                        'message_id' => (int) $message->id,
                        'index' => (int) $index,
                        'item' => $item,
                        'sent_at' => $message->created_at,
                    ];
                }
            });

        return $files;
    }

    /**
     * @return HasMany<ConversationAnalysis, $this>
     */
    public function analyses(): HasMany
    {
        return $this->hasMany(ConversationAnalysis::class);
    }

    /**
     * The answers the owner taught FROM this thread: the badge's reverse
     * provenance ("this chat made the assistant smarter").
     *
     * @return HasMany<KnowledgeDocument, $this>
     */
    public function taughtFaqs(): HasMany
    {
        return $this->hasMany(KnowledgeDocument::class);
    }

    /**
     * The inbox row's preview: one eager load for the whole list instead of
     * a query per thread. Internal notes never pose as the last word.
     *
     * @return HasOne<ConversationMessage, $this>
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(ConversationMessage::class)
            ->ofMany(['id' => 'max'], fn ($query) => $query->where('kind', MessageKind::Message));
    }

    /**
     * The tenant's threads whose contact matches by name or number. What was
     * SAID inside them is the meaning lane's job, over the questions' vectors.
     *
     * @return EloquentCollection<int, static>
     */
    public static function matching(string $term, int $limit): EloquentCollection
    {
        return static::query()
            ->select(['id', 'contact_name', 'contact_phone', 'status', 'last_message_at'])
            ->whereTextMatches(['contact_name', 'contact_phone'], $term)
            ->orderByLeadingMatch('contact_name', $term)
            ->orderByDesc('last_message_at')
            ->limit($limit)
            ->get();
    }
}
