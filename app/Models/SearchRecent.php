<?php

declare(strict_types=1);

namespace App\Models;

use App\Dto\SearchHitDto;
use App\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What a person opened last from the palette. The row stores the hit as it was
 * shown: the service it points at may be renamed or gone by the time she
 * looks again, and a trail that rewrites itself is not a trail.
 */
#[Fillable(['business_id', 'user_id', 'hit_key', 'group_key', 'icon', 'title', 'subtitle', 'url'])]
class SearchRecent extends Model
{
    use BelongsToBusiness;

    /** Enough to be useful, few enough that the list stays scannable. */
    public const int KEEP = 5;

    /**
     * With the framework default the seconds are written and the microseconds
     * dropped, so two picks inside the same second tie — and the tie is what
     * orders this list.
     */
    protected $dateFormat = 'Y-m-d H:i:s.u';

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Writes the pick and drops what falls past the window. The unique key
     * makes a repeat climb instead of being listed twice.
     */
    public static function remember(User $user, SearchHitDto $hit): void
    {
        $row = static::query()->updateOrCreate(
            ['user_id' => $user->id, 'hit_key' => $hit->key],
            [
                'business_id' => $user->business_id,
                'group_key' => $hit->group,
                'icon' => $hit->icon,
                'title' => $hit->title,
                'subtitle' => $hit->subtitle,
                'url' => $hit->url,
            ],
        );

        // Picking the same row again changes no column, and an unchanged save
        // never moves updated_at — which is the only thing that orders this.
        $row->touch();

        $keep = static::query()
            ->where('user_id', $user->id)
            ->orderByDesc('updated_at')
            ->limit(self::KEEP)
            ->pluck('id');

        static::query()->where('user_id', $user->id)->whereNotIn('id', $keep)->delete();
    }

    /**
     * This person's trail, newest first.
     *
     * @return EloquentCollection<int, static>
     */
    public static function forUser(User $user): EloquentCollection
    {
        return static::query()
            ->where('user_id', $user->id)
            ->orderByDesc('updated_at')
            ->limit(self::KEEP)
            ->get();
    }
}
