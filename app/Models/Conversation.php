<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConversationStatus;
use App\Enums\MessageDirection;
use App\Enums\MessageKind;
use App\Services\Tenant;
use App\Traits\BelongsToBusiness;
use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One WhatsApp thread between a business and one customer. The reply
 * worker writes it; the assistant reads it back as memory.
 */
#[Fillable(['business_id', 'customer_id', 'contact_phone', 'contact_name', 'language', 'status', 'escalated_at', 'handoff_reminded_at', 'last_message_at'])]
class Conversation extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<ConversationFactory> */
    use HasFactory;

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
}
