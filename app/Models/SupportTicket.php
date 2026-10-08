<?php

declare(strict_types=1);

namespace App\Models;

use App\Classes\Main\SupportCustomer;
use App\Classes\Main\TicketEvidence;
use App\Enums\SupportTicketKind;
use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use App\Traits\BelongsToBusiness;
use App\Traits\SearchesText;
use Carbon\CarbonInterface;
use Carbon\CarbonInterval;
use Database\Factories\SupportTicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Str;

/**
 * A report from a business: a problem, an idea or a question. The code is what
 * the person is told to quote back, so it is short, unambiguous out loud and
 * free of the letters that look like digits.
 */
#[Fillable(['business_id', 'user_id', 'code', 'kind', 'status', 'priority', 'assigned_to', 'blocked_tried', 'blocked_missing', 'blocked_owner', 'blocked_due', 'blocked_at', 'screen', 'after_help', 'body', 'attachment_path', 'context', 'reply', 'answered_at', 'resolved_at'])]
class SupportTicket extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<SupportTicketFactory> */
    use HasFactory;

    use SearchesText;

    /** Mirrors the column default, so a report created in this request already knows its priority. */
    protected $attributes = ['priority' => 'normal'];

    /** No I, O, 0 or 1: the code gets read out loud over WhatsApp. */
    private const string ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    protected function casts(): array
    {
        return [
            'kind' => SupportTicketKind::class,
            'status' => SupportTicketStatus::class,
            'priority' => SupportTicketPriority::class,
            'context' => 'array',
            'after_help' => 'boolean',
            'answered_at' => 'datetime',
            'resolved_at' => 'datetime',
            'blocked_due' => 'date',
            'blocked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The team member who has it.
     *
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * What the team wrote on it, oldest first: answers and notes together,
     * which is the order a person picking the case up reads them in.
     *
     * @return HasMany<SupportTicketMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class)->orderBy('created_at')->orderBy('id');
    }

    /**
     * The other reports of a business, newest first: a customer who writes for
     * the third time in a month is not the same case as one writing for the first.
     *
     * @return SupportCollection<int, static>
     */
    public static function siblingsOf(int $businessId, int $exceptId, int $limit = 5): SupportCollection
    {
        return static::query()
            ->where('business_id', $businessId)
            ->whereKeyNot($exceptId)
            ->latest()
            ->limit($limit)
            ->get()
            ->toBase();
    }

    /** Who this report is, in what the one answering it needs to know. */
    public function customer(): SupportCustomer
    {
        return new SupportCustomer($this->business, $this->id);
    }

    /**
     * A candidate code. It is NOT checked against the table: both the tenant
     * scope and the RLS policy hide other businesses' rows by design, so a
     * global lookup cannot be honest from inside a tenant. The unique index is
     * the arbiter, and the writer retries on the one-in-33-million collision.
     */
    public static function freshCode(): string
    {
        $size = strlen(self::ALPHABET);

        return 'ATN-'.collect(range(1, 5))
            ->map(fn (): string => self::ALPHABET[random_int(0, $size - 1)])
            ->implode('');
    }

    /**
     * The admin inbox: oldest first, because a queue is answered in the order
     * it arrived. Open ones lead; what is settled sinks below them.
     */
    public function scopeByArrival(Builder $query): Builder
    {
        return $query
            ->orderByRaw("case when status in ('new','open','waiting','blocked') then 0 else 1 end")
            ->orderBy('created_at');
    }

    /**
     * The admin inbox, ready to paint: oldest unanswered first, with the
     * business and the person already loaded. The filters narrow the queue;
     * none of them changes its order.
     *
     * @param  string  $scope  A queue view: see SupportTicketStatus::inScope().
     * @param  string|null  $assignee  A user id, or "none" for the ones nobody has.
     * @return EloquentCollection<int, static>
     */
    public static function inbox(string $scope = 'open', ?string $kind = null, ?int $businessId = null, string $search = '', ?string $assignee = null, int $limit = 100): EloquentCollection
    {
        $statuses = SupportTicketStatus::inScope($scope);
        $term = trim($search);

        return static::query()
            // The whole business, not two columns: each row also says who pays and how.
            ->with(['business.subscription', 'user:id,name', 'assignee:id,name'])
            ->when($statuses !== null, fn (Builder $query): Builder => $query->whereIn('status', $statuses))
            ->when(SupportTicketKind::tryFrom((string) $kind) !== null, fn (Builder $query): Builder => $query->where('kind', $kind))
            ->when($businessId !== null, fn (Builder $query): Builder => $query->where('business_id', $businessId))
            ->when($assignee === 'none', fn (Builder $query): Builder => $query->whereNull('assigned_to'))
            ->when(ctype_digit((string) $assignee), fn (Builder $query): Builder => $query->where('assigned_to', (int) $assignee))
            ->when($term !== '', fn (Builder $query): Builder => $query->where(
                fn (Builder $match): Builder => $match
                    ->whereTextMatches(['body', 'code'], $term)
                    ->orWhereHas('business', fn (Builder $business): Builder => $business->whereTextMatches(['name'], $term)),
            ))
            ->byArrival()
            ->limit($limit)
            ->get();
    }

    /**
     * The businesses that ever reported something, for the filter: asking for
     * all of them would offer a choice that can only come back empty.
     *
     * @return array<int, string> Name by business id.
     */
    public static function reporters(): array
    {
        return Business::query()
            ->whereIn('id', static::query()->select('business_id'))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /** The one that has waited longest for us: the first call of the day. */
    public static function oldestUnanswered(): ?static
    {
        return static::query()
            ->whereIn('status', SupportTicketStatus::inScope('answer'))
            ->orderBy('created_at')
            ->first();
    }

    /**
     * Since when somebody is waiting, on either side: for us while it is new
     * or in progress, for the business once we asked it something. A settled
     * report waits for nobody.
     */
    public function waitingSince(): ?CarbonInterface
    {
        return match ($this->status) {
            SupportTicketStatus::New, SupportTicketStatus::Open => $this->created_at,
            SupportTicketStatus::Waiting => $this->answered_at ?? $this->created_at,
            SupportTicketStatus::Blocked => $this->blocked_at ?? $this->created_at,
            default => null,
        };
    }

    /**
     * Waited on us for longer than its priority allows. An idea runs no clock,
     * whatever its priority says: nobody is stuck while it waits.
     */
    public function isOverdue(): bool
    {
        $since = $this->waitingSince();
        $hours = $this->priority->overdueHours();

        return $this->status->isOurs()
            && $this->kind !== SupportTicketKind::Idea
            && $hours !== null
            && $since !== null
            && $since->diffInHours(now()) >= $hours;
    }

    /** Hours it may wait for our answer, for the tag that says why it is red. */
    public function overdueHours(): ?int
    {
        return $this->priority->overdueHours();
    }

    /** The follow-up date of a blocked report came and went: the promise is broken. */
    public function isBlockedLate(): bool
    {
        return $this->status === SupportTicketStatus::Blocked
            && $this->blocked_due !== null
            && $this->blocked_due->isBefore(today());
    }

    /** Either kind of late: what paints the row. */
    public function isLate(): bool
    {
        return $this->isOverdue() || $this->isBlockedLate();
    }

    /**
     * How many reports sit in each queue, in one query, plus how many of the
     * ones to answer are already late — the red dot on the first tab.
     *
     * @return array{answer: int, waiting: int, blocked: int, resolved: int, late: int, blockedLate: int}
     */
    public static function queueCounts(): array
    {
        $byStatus = static::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $count = fn (string $scope): int => (int) collect(SupportTicketStatus::inScope($scope))->sum(fn (string $status): int => (int) ($byStatus[$status] ?? 0));

        return [
            'answer' => $count('answer'),
            'waiting' => $count('waiting'),
            'blocked' => $count('blocked'),
            'resolved' => $count('resolved'),
            'late' => static::query()->whereIn('status', SupportTicketStatus::inScope('answer'))->get()
                ->filter(fn (self $ticket): bool => $ticket->isOverdue())->count(),
            'blockedLate' => static::query()->where('status', SupportTicketStatus::Blocked)->whereDate('blocked_due', '<', today())->count(),
        ];
    }

    /**
     * How long, in the two largest units: "3d 4h", never "3 days, 4 hours and
     * 12 minutes", which nobody reads at a glance down a column.
     */
    public function waitLabel(): ?string
    {
        $since = $this->waitingSince();

        return $since === null ? null : $since->diffForHumans(now(), ['syntax' => CarbonInterface::DIFF_ABSOLUTE, 'parts' => 2, 'short' => true]);
    }

    /** What it took us, once settled: the figure that says how the queue is really doing. */
    public function resolvedInLabel(): ?string
    {
        return $this->resolved_at === null
            ? null
            : $this->created_at->diffForHumans($this->resolved_at, ['syntax' => CarbonInterface::DIFF_ABSOLUTE, 'parts' => 2, 'short' => true]);
    }

    /**
     * The typical time to settle a report, by kind: the figure that says whether
     * the overdue clock is set where it should be. A median, not an average, so
     * one report that sat for a month does not hide how the rest went.
     *
     * @return array<string, string> Label by kind value; a kind with nothing settled is absent, never "0".
     */
    public static function medianResolution(int $days = 90): array
    {
        return static::query()
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '>=', now()->subDays($days))
            ->get(['kind', 'created_at', 'resolved_at'])
            ->groupBy(fn (self $ticket): string => $ticket->kind->value)
            ->map(function (SupportCollection $tickets): string {
                $seconds = $tickets
                    ->map(fn (self $ticket): int => (int) $ticket->created_at->diffInSeconds($ticket->resolved_at, true))
                    ->sort()
                    ->values();
                $middle = intdiv($seconds->count(), 2);
                $median = $seconds->count() % 2 === 1 ? $seconds[$middle] : intdiv($seconds[$middle - 1] + $seconds[$middle], 2);

                return CarbonInterval::seconds($median)->cascade()->forHumans(['parts' => 2, 'short' => true]);
            })
            ->all();
    }

    /**
     * The report in the work panel, with everything the panel reads loaded in
     * one go: the conversation, who wrote each line, and the customer behind it.
     */
    public static function opened(int $id): ?static
    {
        return static::query()
            ->with(['business.subscription', 'user:id,name,email', 'assignee:id,name', 'messages.author:id,name'])
            ->find($id);
    }

    /** One ticket by id, for the admin acting on a row it just listed. */
    public static function locate(int $id): ?static
    {
        return static::query()->find($id);
    }

    /**
     * Which screens hurt most: the same screen reported over and over is the
     * thing to fix, and a list of single rows never shows that.
     *
     * @return SupportCollection<int, object{screen: string|null, total: int}>
     */
    public static function painPoints(int $top = 3): SupportCollection
    {
        return static::query()
            ->selectRaw('screen, count(*) as total')
            ->whereIn('status', ['new', 'open', 'waiting'])
            ->groupBy('screen')
            ->orderByDesc('total')
            ->limit($top)
            ->get()
            ->map(fn (Model $row): object => (object) [
                'screen' => $row->getAttribute('screen'),
                'total' => (int) $row->getAttribute('total'),
            ]);
    }

    /** What the widget captured, in words: see TicketEvidence. */
    public function evidence(): TicketEvidence
    {
        return new TicketEvidence($this->context);
    }

    /** The first line, for a list that shows one row per ticket. */
    public function excerpt(int $length = 90): string
    {
        return Str::limit(trim((string) preg_replace('/\s+/', ' ', $this->body)), $length);
    }
}
