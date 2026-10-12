<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ComplianceEventKind;
use App\Enums\SubscriptionStatus;
use App\Traits\BelongsToBusiness;
use Database\Factories\ComplianceEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Lang;

/**
 * One line of a business's file: what happened and, when a person decided it,
 * who. Written when it happens and never edited.
 */
#[Fillable(['business_id', 'kind', 'detail', 'actor_id', 'occurred_at'])]
class ComplianceEvent extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<ComplianceEventFactory> */
    use HasFactory;

    /** How far back "recent" reaches: a fault older than this no longer colours the standing. */
    public const int RECENT_DAYS = 90;

    /** Standings, worst first: the first one that applies is the one a business wears. */
    public const string SUSPENDED = 'suspended';

    public const string DEBT = 'debt';

    public const string OBSERVED = 'observed';

    public const string CLEAR = 'clear';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ComplianceEventKind::class,
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * What it was, in words. A moderation catch stores codes ("logo · sexual/minors")
     * so the file survives a rewording; the person reads Spanish. Free text (an
     * appeal, a refusal reason) is somebody's own and is shown as written.
     */
    public function readableDetail(): ?string
    {
        if (blank($this->detail)) {
            return null;
        }

        if (! in_array($this->kind, [
            ComplianceEventKind::ContentRejected,
            ComplianceEventKind::ContentSevere,
            ComplianceEventKind::Suspended,
            ComplianceEventKind::SuspensionLifted,
        ], true)) {
            return $this->detail;
        }

        return collect(explode(' · ', $this->detail))
            ->map(function (string $token): string {
                $key = 'compliance.codes.'.str_replace('/', '_', $token);

                return Lang::has($key) ? __($key) : $token;
            })
            ->implode(' · ');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * The businesses with something in their file, worst standing and latest
     * event first. The standing is read from the business as it is TODAY: a
     * suspension lifted last month does not make it suspended now.
     *
     * @param  string  $filter  '' (all), suspended, debt or recent.
     * @return Collection<int, array{id: int, name: string, standing: string, recent: int, total: int, last_at: Carbon|null, last_kind: ComplianceEventKind|null}>
     */
    public static function board(string $filter = '', string $search = '', int $limit = 30): Collection
    {
        $since = now()->subDays(self::RECENT_DAYS);

        $businesses = self::boardQuery($filter, $search)
            // The whole row: `latestOfMany` joins on `business_id`, so a column list is ambiguous.
            ->with('subscription')
            ->withCount([
                'complianceEvents as events_total',
                'complianceEvents as events_recent' => fn (Builder $query): Builder => $query->where('occurred_at', '>=', $since),
            ])
            ->withMax('complianceEvents as last_at', 'occurred_at')
            ->orderByDesc('last_at')
            ->limit($limit)
            ->get();

        $lastKinds = self::query()
            ->whereIn('business_id', $businesses->modelKeys())
            ->orderByDesc('occurred_at')
            ->get(['business_id', 'kind'])
            ->unique('business_id')
            ->pluck('kind', 'business_id');

        return $businesses
            ->map(fn (Business $business): array => [
                'id' => $business->id,
                'name' => $business->name,
                'standing' => self::standingOf($business, (int) $business->events_recent),
                'recent' => (int) $business->events_recent,
                'total' => (int) $business->events_total,
                'last_at' => $business->last_at === null ? null : Carbon::parse($business->last_at),
                'last_kind' => $lastKinds->get($business->id),
            ])
            ->sortBy(fn (array $row): int => array_search($row['standing'], [self::SUSPENDED, self::DEBT, self::OBSERVED, self::CLEAR], true))
            ->values();
    }

    /** How many businesses the filter matches, for the "show more" that counts what is left. */
    public static function boardCount(string $filter = '', string $search = ''): int
    {
        return self::boardQuery($filter, $search)->count();
    }

    /**
     * The businesses with a file, narrowed by what she asked for.
     *
     * @return Builder<Business>
     */
    private static function boardQuery(string $filter, string $search): Builder
    {
        $since = now()->subDays(self::RECENT_DAYS);

        return Business::query()
            ->whereHas('complianceEvents')
            ->when($filter === self::SUSPENDED, fn (Builder $query): Builder => $query->whereNotNull('suspended_at'))
            ->when($filter === self::DEBT, fn (Builder $query): Builder => $query->whereHas(
                'subscription',
                fn (Builder $sub): Builder => $sub->whereIn('status', [SubscriptionStatus::PastDue, SubscriptionStatus::Paused]),
            ))
            ->when($filter === 'recent', fn (Builder $query): Builder => $query->whereHas(
                'complianceEvents',
                fn (Builder $events): Builder => $events->where('occurred_at', '>=', $since),
            ))
            ->when(trim($search) !== '', fn (Builder $query): Builder => $query->whereTextMatches(['name'], trim($search)));
    }

    /** What a business is today, from the worst thing that is still true of it. */
    public static function standingOf(Business $business, int $recentEvents): string
    {
        return match (true) {
            $business->isSuspended() => self::SUSPENDED,
            in_array($business->subscription?->status, [SubscriptionStatus::PastDue, SubscriptionStatus::Paused], true) => self::DEBT,
            $recentEvents > 0 => self::OBSERVED,
            default => self::CLEAR,
        };
    }

    /** The business a file belongs to, with the subscription its standing is read from. */
    public static function businessOf(int $businessId): ?Business
    {
        return Business::query()->with('subscription')->find($businessId);
    }

    /**
     * One business's file, newest first.
     *
     * @return Collection<int, self>
     */
    public static function timelineOf(int $businessId): Collection
    {
        return self::query()
            ->where('business_id', $businessId)
            ->with('actor:id,name')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->get();
    }

    /** Faults that came back: more than one of the same kind is a pattern, one is an accident. */
    public static function repeatsOf(int $businessId): int
    {
        return (int) self::query()
            ->where('business_id', $businessId)
            ->whereIn('kind', array_map(fn (ComplianceEventKind $kind): string => $kind->value, array_filter(
                ComplianceEventKind::cases(),
                fn (ComplianceEventKind $kind): bool => $kind->isFault(),
            )))
            ->selectRaw('count(*) - count(distinct kind) as repeats')
            ->value('repeats');
    }
}
