<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CustomerSentiment;
use App\Traits\BelongsToBusiness;
use Database\Factories\ConversationAnalysisFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One AI reading of a finished stretch of a thread. */
#[Fillable(['business_id', 'conversation_id', 'first_message_id', 'last_message_id', 'sentiment'])]
class ConversationAnalysis extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<ConversationAnalysisFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sentiment' => CustomerSentiment::class,
        ];
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * @return HasMany<ConversationQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(ConversationQuestion::class);
    }

    /**
     * Readings where the customer was left annoyed, newest first. One row per
     * thread: a long thread read three times is one unhappy customer, not
     * three. Demo businesses are out — the landing's phone is not a customer.
     *
     * @return EloquentCollection<int, static>
     */
    public static function upset(int $sinceDays): EloquentCollection
    {
        return static::query()
            ->where('sentiment', CustomerSentiment::Negative)
            ->where('created_at', '>=', now()->subDays($sinceDays))
            ->whereNotIn('business_id', Business::demoIds())
            ->whereIn('id', static::query()
                ->selectRaw('max(id)')
                ->where('sentiment', CustomerSentiment::Negative)
                ->groupBy('conversation_id'))
            ->with(['business:id,name', 'conversation:id,contact_name,contact_phone'])
            ->latest()
            ->get();
    }
}
