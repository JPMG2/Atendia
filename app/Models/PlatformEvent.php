<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PlatformEventKind;
use App\Enums\PlatformEventSeverity;
use App\Enums\PlatformEventState;
use Database\Factories\PlatformEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * One line of what the platform has to tell its admin. The platform's own,
 * not a tenant's: `about_business_id` says who it concerns and nothing scopes
 * it, because only the admin panel ever reads this table.
 */
#[Fillable([
    'kind', 'severity', 'state', 'about_business_id', 'dedupe_key', 'detail', 'occurrences',
    'first_seen_at', 'last_seen_at', 'notified_at', 'emailed_at', 'acknowledged_at', 'acknowledged_by', 'resolved_at',
])]
class PlatformEvent extends Model
{
    /** @use HasFactory<PlatformEventFactory> */
    use HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'kind' => PlatformEventKind::class,
            'severity' => PlatformEventSeverity::class,
            'state' => PlatformEventState::class,
            'occurrences' => 'integer',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'notified_at' => 'datetime',
            'emailed_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Business, $this> */
    public function aboutBusiness(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'about_business_id');
    }

    /** @return BelongsTo<User, $this> */
    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    /**
     * What still has a place on the board: open or seen, never resolved.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('state', '!=', PlatformEventState::Resolved->value);
    }

    /**
     * The board, worst first and newest first inside each severity, one page
     * at a time: a list that grows pages in SQL and never loads the table.
     *
     * @param  list<PlatformEventKind>|null  $kinds  null = every kind
     * @return Collection<int, self>
     */
    public static function board(int $limit, ?PlatformEventSeverity $severity = null, ?array $kinds = null, ?int $businessId = null, bool $withResolved = false, bool $unseenOnly = false): Collection
    {
        return self::query()
            ->with(['aboutBusiness:id,name', 'acknowledgedBy:id,name'])
            ->when(! $withResolved, fn (Builder $query) => $query->active())
            ->when($unseenOnly, fn (Builder $query) => $query->where('state', PlatformEventState::Open->value))
            ->when($severity !== null, fn (Builder $query) => $query->where('severity', $severity->value))
            ->when($kinds !== null, fn (Builder $query) => $query->whereIn('kind', array_map(fn (PlatformEventKind $kind): string => $kind->value, $kinds)))
            ->when($businessId !== null, fn (Builder $query) => $query->where('about_business_id', $businessId))
            ->orderByRaw("CASE severity WHEN 'critical' THEN 3 WHEN 'important' THEN 2 ELSE 1 END DESC")
            ->orderByDesc('last_seen_at')
            ->limit($limit)
            ->get();
    }

    /**
     * What is waiting, by severity. Only active rows count: a tile that
     * included what was resolved would never go back to zero.
     *
     * @return array{critical: int, important: int, info: int, unseen: int}
     */
    public static function counts(): array
    {
        $rows = self::query()->active()
            ->selectRaw('severity, count(*) as total, count(*) filter (where state = ?) as unseen', [PlatformEventState::Open->value])
            ->groupBy('severity')
            ->get();

        $count = fn (PlatformEventSeverity $severity): int => (int) ($rows->firstWhere('severity', $severity)?->total ?? 0);

        return [
            'critical' => $count(PlatformEventSeverity::Critical),
            'important' => $count(PlatformEventSeverity::Important),
            'info' => $count(PlatformEventSeverity::Info),
            'unseen' => (int) $rows->sum('unseen'),
        ];
    }

    /** Marks one line seen by id; a line that was resolved meanwhile is ignored. */
    public static function acknowledgeById(int $id, User $by): void
    {
        self::query()->whereKey($id)->first()?->acknowledge($by);
    }

    /** The line's state after an action on it (`open`, `acknowledged`, `resolved`); null when it is gone. */
    public static function stateOf(int $id): ?string
    {
        return self::query()->whereKey($id)->first()?->state->value;
    }

    /** Somebody saw it. A second look changes nothing: the first one is the one that counts. */
    public function acknowledge(User $by): void
    {
        if ($this->state !== PlatformEventState::Open) {
            return;
        }

        $this->forceFill([
            'state' => PlatformEventState::Acknowledged,
            'acknowledged_at' => now(),
            'acknowledged_by' => $by->id,
        ])->save();
    }
}
