<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CustomerSentiment;
use App\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One AI reading of a finished stretch of a thread. */
#[Fillable(['business_id', 'conversation_id', 'first_message_id', 'last_message_id', 'sentiment', 'prompt_tokens', 'completion_tokens'])]
class ConversationAnalysis extends Model
{
    use BelongsToBusiness;

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
}
