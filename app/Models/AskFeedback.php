<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A thumb on one "Ask AtendIa" answer: what to fix in the assistant, read by the admin. */
#[Fillable(['business_id', 'user_id', 'question', 'answer', 'rating'])]
class AskFeedback extends Model
{
    use BelongsToBusiness;

    public const string UP = 'up';

    public const string DOWN = 'down';

    protected $table = 'ask_feedback';

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The thumbs down on "Pregúntale", newest first: every one is an answer
     * that was wrong in front of the only person who can tell.
     *
     * @return Collection<int, self>
     */
    public static function rejected(int $limit = 50): Collection
    {
        return self::query()
            ->where('rating', self::DOWN)
            ->with(['business:id,name', 'user:id,name'])
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Good out of total, and the TOTAL alongside. A share on its own lies:
     * two thumbs up out of two marks is not a perfect assistant, it is two
     * clicks — and almost nobody ever marks anything.
     *
     * @return array{good: int, bad: int, total: int, share: ?float}
     */
    public static function score(): array
    {
        $marks = self::query()->get(['rating']);
        $good = $marks->where('rating', self::UP)->count();
        $total = $marks->count();

        return [
            'good' => $good,
            'bad' => $total - $good,
            'total' => $total,
            'share' => $total === 0 ? null : round($good / $total * 100),
        ];
    }

    public static function record(Business $business, ?int $userId, string $question, string $answer, string $rating): self
    {
        return self::query()->create([
            'business_id' => $business->id,
            'user_id' => $userId,
            'question' => $question,
            'answer' => $answer,
            'rating' => $rating,
        ]);
    }
}
