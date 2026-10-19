<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MentionStatus;
use App\Traits\BelongsToBusiness;
use Database\Factories\BusinessMentionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A business's answer to the offer of being mentioned on the platform's
 * networks, with everything the admin needs to check the follow and publish.
 */
#[Fillable([
    'business_id', 'social_network_id', 'goal_threshold', 'conversations', 'status',
    'business_account_url', 'platform_accounts', 'caption',
    'answered_at', 'verified_at', 'verified_by', 'published_at', 'post_url',
])]
class BusinessMention extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<BusinessMentionFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => MentionStatus::class,
            'platform_accounts' => 'array',
            'goal_threshold' => 'integer',
            'conversations' => 'integer',
            'answered_at' => 'datetime',
            'verified_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<SocialNetwork, $this> */
    public function socialNetwork(): BelongsTo
    {
        return $this->belongsTo(SocialNetwork::class);
    }

    /** @return BelongsTo<User, $this> */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * The admin's desk for one stage, the oldest answer first: what has waited
     * longest is what she should look at. One row past the page tells whether
     * there is more to load.
     *
     * @return Collection<int, BusinessMention>
     */
    public static function desk(MentionStatus $stage, int $limit): Collection
    {
        return static::query()
            ->with(['business:id,name', 'socialNetwork:id,name,icon'])
            ->where('status', $stage)
            ->orderBy($stage === MentionStatus::Published ? 'published_at' : 'answered_at', $stage === MentionStatus::Published ? 'desc' : 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * How many sit at each stage of the desk, for the tabs and the home tile.
     *
     * @return array{pending: int, not_following: int, ready: int, published: int}
     */
    public static function deskCounts(): array
    {
        $counts = static::query()
            ->whereIn('status', [MentionStatus::Pending, MentionStatus::NotFollowing, MentionStatus::Ready, MentionStatus::Published])
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'pending' => (int) ($counts[MentionStatus::Pending->value] ?? 0),
            'not_following' => (int) ($counts[MentionStatus::NotFollowing->value] ?? 0),
            'ready' => (int) ($counts[MentionStatus::Ready->value] ?? 0),
            'published' => (int) ($counts[MentionStatus::Published->value] ?? 0),
        ];
    }
}
