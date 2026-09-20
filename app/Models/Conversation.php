<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConversationStatus;
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
#[Fillable(['business_id', 'customer_id', 'contact_phone', 'contact_name', 'language', 'status', 'last_message_at'])]
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
            'last_message_at' => 'datetime',
        ];
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
     * The inbox row's preview: one eager load for the whole list instead of
     * a query per thread.
     *
     * @return HasOne<ConversationMessage, $this>
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(ConversationMessage::class)->latestOfMany();
    }
}
