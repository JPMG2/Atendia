<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToBusiness;
use Database\Factories\AssistantRatingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection as SupportCollection;

/** One mark the business put on one reply of its assistant. */
#[Fillable(['business_id', 'conversation_message_id', 'user_id', 'is_good', 'reason'])]
class AssistantRating extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<AssistantRatingFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_good' => 'boolean',
        ];
    }

    /** @return BelongsTo<ConversationMessage, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(ConversationMessage::class, 'conversation_message_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * How one thread's replies were marked, so the thumbs show their state.
     *
     * @return array<int, bool> message id => true when it was marked as good
     */
    public static function forThread(int $conversationId): array
    {
        return static::query()
            ->whereIn('conversation_message_id', ConversationMessage::query()
                ->where('conversation_id', $conversationId)
                ->select('id'))
            ->pluck('is_good', 'conversation_message_id')
            ->map(fn (mixed $good): bool => (bool) $good)
            ->all();
    }

    /**
     * The replies marked as wrong, newest first, with the thread behind them.
     * Demo businesses are out: the landing's own phone is not a customer.
     *
     * @return EloquentCollection<int, static>
     */
    public static function rejected(int $sinceDays): EloquentCollection
    {
        return static::query()
            ->where('is_good', false)
            ->where('created_at', '>=', now()->subDays($sinceDays))
            ->whereNotIn('business_id', Business::demoIds())
            ->with(['business:id,name', 'message:id,conversation_id,body', 'message.conversation:id,contact_name,contact_phone'])
            ->latest()
            ->get();
    }

    /**
     * The board per business: how its assistant is being marked, worst first.
     * A business nobody ever marked does not appear — inventing a 0% for it
     * would accuse an assistant that may be answering perfectly.
     *
     * @return Collection<int, object{business_id: int, name: string, good: int, bad: int, total: int}>
     */
    public static function byBusiness(): SupportCollection
    {
        return static::query()
            ->selectRaw('assistant_ratings.business_id, businesses.name')
            ->selectRaw('count(*) filter (where is_good) as good')
            ->selectRaw('count(*) filter (where not is_good) as bad')
            ->selectRaw('count(*) as total')
            ->join('businesses', 'businesses.id', '=', 'assistant_ratings.business_id')
            ->groupBy('assistant_ratings.business_id', 'businesses.name')
            ->orderByDesc('bad')
            ->orderByDesc('total')
            ->get()
            ->toBase();
    }

    /**
     * How the assistant is doing for one business, and over HOW MANY marks.
     * The share alone lies: two thumbs up out of two replies read as a perfect
     * assistant, and almost nobody ever marks anything.
     *
     * @return array{good: int, bad: int, total: int, share: ?float}
     */
    public static function scoreFor(int $businessId): array
    {
        $marks = static::query()->where('business_id', $businessId)->get(['is_good']);
        $good = $marks->where('is_good', true)->count();
        $total = $marks->count();

        return [
            'good' => $good,
            'bad' => $total - $good,
            'total' => $total,
            'share' => $total === 0 ? null : round($good / $total * 100),
        ];
    }
}
