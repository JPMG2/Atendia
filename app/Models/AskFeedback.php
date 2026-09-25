<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** A thumb on one "Ask AtendIa" answer: what to fix in the assistant, read by the admin. */
#[Fillable(['business_id', 'user_id', 'question', 'answer', 'rating'])]
class AskFeedback extends Model
{
    use BelongsToBusiness;

    public const string UP = 'up';

    public const string DOWN = 'down';

    protected $table = 'ask_feedback';

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
