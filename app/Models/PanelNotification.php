<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PanelNotificationType;
use App\Events\PanelNotificationRaised;
use App\Traits\BelongsToBusiness;
use Carbon\CarbonInterface;
use Database\Factories\PanelNotificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * One event in the bell's inbox. The notice belongs to the business; the read
 * mark belongs to each person, so a team of two never hides news from itself.
 */
#[Fillable(['business_id', 'type', 'dedupe_key', 'payload', 'url'])]
class PanelNotification extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<PanelNotificationFactory> */
    use HasFactory;

    use MassPrunable;

    /**
     * An inbox nobody empties grows forever. Past the window the row is
     * history: what it pointed at was either solved or is long gone.
     *
     * @return Builder<PanelNotification>
     */
    public function prunable(): Builder
    {
        return self::query()->where('created_at', '<', now()->subDays((int) config('atendia.bell.keep_days')));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PanelNotificationType::class,
            'payload' => 'array',
        ];
    }

    /**
     * Raise one event, or bring the same fact back to the top: reviving drops
     * the read marks, because a customer who waits again is news again. Pass
     * `$revive` false where a sweep re-reports an UNCHANGED fact every few
     * minutes — reviving there would mark it unread on every pass and nobody
     * could ever read it away.
     *
     * @param  array<string, string|int>  $payload
     */
    public static function raise(
        Business $business,
        PanelNotificationType $type,
        string $dedupeKey,
        array $payload,
        ?string $url = null,
        bool $revive = true,
    ): self {
        $notice = self::query()->firstOrNew([
            'business_id' => $business->id,
            'dedupe_key' => $dedupeKey,
        ]);

        if ($notice->exists && $revive) {
            $notice->readers()->detach();
            $notice->created_at = now();
        }

        $notice->fill(['type' => $type, 'payload' => $payload, 'url' => $url]);
        $notice->save();

        PanelNotificationRaised::dispatch((int) $business->id);

        return $notice;
    }

    /**
     * How many the bell has to light up for: what this person has not opened.
     * Zero means the dot stays off — a badge that is always lit teaches people
     * to stop looking at it.
     */
    public static function unreadCountFor(User $user): int
    {
        return self::query()->visibleTo($user)->unreadBy($user)->count();
    }

    /**
     * The inbox as the panel draws it: freshest first, cut to one screenful,
     * already told which ones this person has read.
     *
     * @return Collection<int, self>
     */
    public static function feedFor(User $user, int $limit = 20): Collection
    {
        return self::query()
            ->visibleTo($user)
            ->withExists(['readers as read' => fn (Builder $reader): Builder => $reader->whereKey($user->id)])
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Notices this person has not read yet.
     *
     * @param  Builder<PanelNotification>  $query
     */
    public function scopeUnreadBy(Builder $query, User $user): void
    {
        $query->whereDoesntHave('readers', fn (Builder $reader): Builder => $reader->whereKey($user->id));
    }

    /**
     * The kinds this person did not mute. Muting hides the row from THEM, never
     * from the business: a teammate who wants it still gets it.
     *
     * @param  Builder<PanelNotification>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $muted = $user->mutedBellTypes();

        $query->when($muted !== [], fn (Builder $visible): Builder => $visible->whereNotIn('type', $muted));
    }

    /** Where a row leads, read from the row itself — never from the click. */
    public static function urlOf(int $id): ?string
    {
        return self::query()->whereKey($id)->value('url');
    }

    /** One row opened in the panel: found inside the tenant's own inbox. */
    public static function markOneReadFor(int $id, User $user): void
    {
        self::query()->whereKey($id)->first()?->markReadBy($user);
    }

    /** Idempotent: opening the same row twice must not fail on the unique key. */
    public function markReadBy(User $user): void
    {
        $this->readers()->syncWithoutDetaching([$user->id => ['read_at' => now()]]);
    }

    /**
     * The escape hatch for whoever fell behind, without opening each row. One
     * statement, not one per row: the pivot is written through the builder
     * because sixty days of unread notices is a round trip each otherwise.
     */
    public static function markAllReadFor(User $user): void
    {
        $unread = self::query()->visibleTo($user)->unreadBy($user)->pluck('id');

        if ($unread->isEmpty()) {
            return;
        }

        DB::table('panel_notification_reads')->insertOrIgnore(
            $unread->map(fn (int $id): array => [
                'panel_notification_id' => $id,
                'user_id' => $user->id,
                'read_at' => now(),
            ])->all(),
        );
    }

    /**
     * The day the row belongs to, on the BUSINESS clock: grouping by UTC puts
     * a 21:30 notice under "yesterday" for a business in Buenos Aires. The zone
     * comes in because every row of one feed shares it — reading it off each
     * row's own business is a query per line.
     */
    public function localDay(string $timezone): CarbonInterface
    {
        return $this->created_at->copy()->setTimezone($timezone)->startOfDay();
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function readers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'panel_notification_reads')->withPivot('read_at');
    }
}
