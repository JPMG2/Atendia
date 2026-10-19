<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MentionStatus;
use Database\Factories\SocialFollowerCountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/** The followers one of the platform's accounts had on a day. */
#[Fillable(['social_link_id', 'followers', 'counted_on', 'counted_by'])]
class SocialFollowerCount extends Model
{
    /** @use HasFactory<SocialFollowerCountFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'followers' => 'integer',
            'counted_on' => 'date',
        ];
    }

    /** @return BelongsTo<SocialLink, $this> */
    public function socialLink(): BelongsTo
    {
        return $this->belongsTo(SocialLink::class);
    }

    /**
     * One line per registered account of the platform: the latest count, the
     * one before it, what changed and how many mentions went out in between.
     * An account never counted still has its line, with no number: "missing"
     * is not zero.
     *
     * @return Collection<int, array{link: SocialLink, latest: ?self, change: ?int, mentions: int, stale: bool}>
     */
    public static function board(): Collection
    {
        $links = Company::current()?->socialLinks()->with('socialNetwork:id,name')->orderBy('sort_order')->get() ?? collect();
        $staleAfter = (int) config('atendia.mentions.followers_stale_days');

        return $links->map(function (SocialLink $link) use ($staleAfter): array {
            [$latest, $previous] = self::query()
                ->where('social_link_id', $link->id)
                ->orderByDesc('counted_on')
                ->limit(2)
                ->get()
                ->pad(2, null)
                ->all();

            return [
                'link' => $link,
                'latest' => $latest,
                'change' => $latest !== null && $previous !== null ? $latest->followers - $previous->followers : null,
                'mentions' => $latest !== null && $previous !== null
                    ? BusinessMention::query()
                        ->where('status', MentionStatus::Published)
                        ->where('social_network_id', $link->social_network_id)
                        ->whereBetween('published_at', [$previous->counted_on->startOfDay(), $latest->counted_on->endOfDay()])
                        ->count()
                    : 0,
                'stale' => $latest === null || $latest->counted_on->lt(today()->subDays($staleAfter)),
            ];
        })->values();
    }
}
