<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LogProblemState;
use App\Traits\SearchesText;
use Carbon\CarbonInterface;
use Database\Factories\LogProblemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * One kind of thing the logs keep saying, however many lines it took: the unit
 * the logs screen works with. It carries the state somebody gave it, so a
 * handled problem stops asking for attention and a returning one says so.
 *
 * Platform-level, so no `business_id` and no tenant scope.
 */
#[Fillable([
    'fingerprint', 'level', 'title', 'exception', 'location', 'first_seen_at', 'last_seen_at', 'last_file',
    'state', 'state_changed_at', 'state_changed_by', 'regressed_at',
])]
class LogProblem extends Model
{
    /** @use HasFactory<LogProblemFactory> */
    use HasFactory;

    use SearchesText;

    /** The periods the screen offers, and how many hours back each one reaches. */
    public const array RANGES = ['hour' => 1, 'day' => 24, 'week' => 168, 'month' => 720];

    /** How long the hourly history is kept. */
    public const int RETENTION_DAYS = 30;

    /** Enough occurrences in the last 24 h, and at least double the 24 h before, to say it is getting worse. */
    public const int WORSE_MIN = 3;

    public const int WORSE_FACTOR = 2;

    /** @return array<string, string|class-string> */
    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'state_changed_at' => 'datetime',
            'regressed_at' => 'datetime',
            'state' => LogProblemState::class,
        ];
    }

    /** @return HasMany<LogProblemHour, $this> */
    public function hours(): HasMany
    {
        return $this->hasMany(LogProblemHour::class);
    }

    /** One problem by id, or null when it is gone (the history rolls off after a while). */
    public static function byId(?int $id): ?self
    {
        return $id === null ? null : self::query()->find($id);
    }

    /** The first hour a period reaches: hours are the grain, so "1 hour" is the last two buckets. */
    public static function since(string $range): CarbonInterface
    {
        $hours = self::RANGES[$range] ?? self::RANGES['day'];

        return now()->subHours($hours)->startOfHour();
    }

    /**
     * The problems of one state that happened in the period, newest first, with
     * how many times each did. A page of them and how many there are in all:
     * the list grows with the platform, so it is cut in SQL.
     *
     * @param  string  $level  '' | error | warning
     * @param  string  $focus  '' | new | worse: what a tile of the screen asks for
     * @return array{rows: Collection<int, self>, total: int}
     */
    public static function board(string $range, LogProblemState $state, string $level = '', string $search = '', string $focus = '', int $limit = 25): array
    {
        $since = self::since($range);
        $hoursSince = fn (Builder $query) => $query->where('hour', '>=', $since);

        $query = self::query()
            ->where('state', $state)
            ->whereHas('hours', $hoursSince)
            ->when(in_array($level, ['error', 'warning'], true), fn (Builder $q) => $q->where('level', $level))
            ->when(trim($search) !== '', fn (Builder $q) => $q->whereTextMatches(['title', 'exception', 'location'], trim($search)))
            ->when($focus === 'new', fn (Builder $q) => $q->where('first_seen_at', '>=', now()->subDay()))
            ->when($focus === 'worse', fn (Builder $q) => $q->where(
                fn (Builder $inner) => $inner->whereIn('id', self::worseIds())->orWhereNotNull('regressed_at'),
            ));

        $total = (clone $query)->count();

        $worse = self::worseIds();
        $rows = $query
            ->withSum(['hours as window_count' => $hoursSince], 'occurrences')
            ->orderByDesc('last_seen_at')
            ->limit($limit)
            ->get()
            ->each(fn (self $problem) => $problem->setAttribute('is_worse', in_array($problem->id, $worse, true)));

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * How many problems each tab holds for the period.
     *
     * @return array{active: int, resolved: int, muted: int}
     */
    public static function tally(string $range): array
    {
        $since = self::since($range);

        $counts = self::query()
            ->whereHas('hours', fn (Builder $query) => $query->where('hour', '>=', $since))
            ->selectRaw('state, count(*) as n')
            ->groupBy('state')
            ->pluck('n', 'state');

        return [
            'active' => (int) ($counts[LogProblemState::Active->value] ?? 0),
            'resolved' => (int) ($counts[LogProblemState::Resolved->value] ?? 0),
            'muted' => (int) ($counts[LogProblemState::Muted->value] ?? 0),
        ];
    }

    /**
     * The tiles on top. "New" and "worse" do not depend on the period: they are
     * about the last day by definition.
     *
     * @return array{new: int, worse: int, open: int, last_error_at: ?CarbonInterface}
     */
    public static function summary(string $range): array
    {
        $active = self::query()->where('state', LogProblemState::Active);

        $lastError = (clone $active)->where('level', 'error')->max('last_seen_at');

        return [
            'new' => (clone $active)->where('first_seen_at', '>=', now()->subDay())->count(),
            'worse' => (clone $active)->where(
                fn (Builder $query) => $query->whereIn('id', self::worseIds())->orWhereNotNull('regressed_at'),
            )->count(),
            'open' => self::tally($range)['active'],
            'last_error_at' => $lastError === null ? null : now()->parse($lastError),
        ];
    }

    /**
     * The active problems that are happening much more than the day before.
     *
     * @return list<int>
     */
    public static function worseIds(): array
    {
        $today = now()->subDay();
        $before = now()->subDays(2);

        return LogProblemHour::query()
            ->selectRaw(
                'log_problem_id, sum(case when hour >= ? then occurrences else 0 end) as current_day, sum(case when hour < ? then occurrences else 0 end) as previous_day',
                [$today, $today],
            )
            ->where('hour', '>=', $before)
            ->whereHas('problem', fn (Builder $query) => $query->where('state', LogProblemState::Active))
            ->groupBy('log_problem_id')
            ->get()
            ->filter(fn (LogProblemHour $row): bool => (int) $row->getAttribute('current_day') >= self::WORSE_MIN
                && (int) $row->getAttribute('current_day') >= self::WORSE_FACTOR * (int) $row->getAttribute('previous_day'))
            ->pluck('log_problem_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * The last 24 hours of each problem, oldest bucket first, with a zero where
     * nothing happened: the sparkline needs the quiet hours too.
     *
     * @param  list<int>  $ids
     * @return array<int, list<int>>
     */
    public static function sparks(array $ids): array
    {
        $start = now()->startOfHour()->subHours(23);
        $sparks = array_fill_keys($ids, array_fill(0, 24, 0));

        LogProblemHour::query()
            ->whereIn('log_problem_id', $ids)
            ->where('hour', '>=', $start)
            ->get()
            ->each(function (LogProblemHour $row) use (&$sparks, $start): void {
                $slot = (int) floor($start->diffInHours($row->hour, false));

                if ($slot >= 0 && $slot < 24) {
                    $sparks[$row->log_problem_id][$slot] += $row->occurrences;
                }
            });

        return $sparks;
    }

    /**
     * How many times it happened in the last day, week and month.
     *
     * @return array{day: int, week: int, month: int}
     */
    public function totals(): array
    {
        $sum = fn (string $range): int => (int) $this->hours()->where('hour', '>=', self::since($range))->sum('occurrences');

        return ['day' => $sum('day'), 'week' => $sum('week'), 'month' => $sum('month')];
    }

    /** What the row says about itself: back after being resolved, new today, or getting worse. */
    public function tag(): ?string
    {
        return match (true) {
            $this->state === LogProblemState::Active && $this->regressed_at !== null => 'back',
            $this->first_seen_at->gte(now()->subDay()) => 'new',
            (bool) $this->getAttribute('is_worse') => 'worse',
            default => null,
        };
    }

    /** Where it comes from, short enough for a second line: the exception class and the file that threw it. */
    public function origin(): string
    {
        $class = $this->exception === null ? null : Str::afterLast($this->exception, '\\');
        $place = $this->location === null ? null : basename(Str::beforeLast($this->location, ':')).':'.Str::afterLast($this->location, ':');

        return implode(' · ', array_filter([$class, $place]));
    }

    /** Moves it to a state. Any manual move closes the "came back" flag: somebody has looked. */
    public function moveTo(LogProblemState $state, ?User $by): void
    {
        $this->forceFill([
            'state' => $state,
            'state_changed_at' => now(),
            'state_changed_by' => $by?->id,
            'regressed_at' => null,
        ])->save();
    }
}
