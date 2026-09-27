<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ModerationSeverity;
use App\Traits\BelongsToBusiness;
use Database\Factories\ModerationFlagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/** One catch of content moderation: the fingerprint and the verdict, never the content. */
#[Fillable(['business_id', 'source', 'kind', 'severity', 'category', 'score', 'fingerprint'])]
class ModerationFlag extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<ModerationFlagFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'severity' => ModerationSeverity::class,
            'score' => 'decimal:4',
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * The admin desk: unreviewed first, newest on top, with who they belong to.
     *
     * @return Collection<int, self>
     */
    public static function reviewQueue(): Collection
    {
        return static::query()->whereNull('reviewed_at')->with('business')->latest()->get();
    }

    /** @return Collection<int, self> */
    public static function recentlyReviewed(int $limit = 20): Collection
    {
        return static::query()->whereNotNull('reviewed_at')->with(['business', 'reviewer'])->latest('reviewed_at')->limit($limit)->get();
    }

    public static function pendingCount(): int
    {
        return static::query()->whereNull('reviewed_at')->count();
    }
}
