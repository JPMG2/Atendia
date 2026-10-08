<?php

declare(strict_types=1);

namespace App\Models;

use App\Classes\Main\TicketEvidence;
use App\Enums\SupportTicketKind;
use App\Enums\SupportTicketStatus;
use App\Traits\BelongsToBusiness;
use App\Traits\SearchesText;
use Carbon\CarbonInterface;
use Database\Factories\SupportTicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Str;

/**
 * A report from a business: a problem, an idea or a question. The code is what
 * the person is told to quote back, so it is short, unambiguous out loud and
 * free of the letters that look like digits.
 */
#[Fillable(['business_id', 'user_id', 'code', 'kind', 'status', 'screen', 'after_help', 'body', 'attachment_path', 'context', 'reply', 'answered_at', 'resolved_at'])]
class SupportTicket extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<SupportTicketFactory> */
    use HasFactory;

    use SearchesText;

    /** No I, O, 0 or 1: the code gets read out loud over WhatsApp. */
    private const string ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    protected function casts(): array
    {
        return [
            'kind' => SupportTicketKind::class,
            'status' => SupportTicketStatus::class,
            'context' => 'array',
            'after_help' => 'boolean',
            'answered_at' => 'datetime',
            'resolved_at' => 'datetime',
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
            ->orderByRaw("case when status in ('new','open','waiting') then 0 else 1 end")
            ->orderBy('created_at');
    }

    /**
     * The admin inbox, ready to paint: oldest unanswered first, with the
     * business and the person already loaded. The filters narrow the queue;
     * none of them changes its order.
     *
     * @param  string  $scope  A queue view: see SupportTicketStatus::inScope().
     * @return EloquentCollection<int, static>
     */
    public static function inbox(string $scope = 'open', ?string $kind = null, ?int $businessId = null, string $search = '', int $limit = 100): EloquentCollection
    {
        $statuses = SupportTicketStatus::inScope($scope);
        $term = trim($search);

        return static::query()
            ->with(['business:id,name', 'user:id,name'])
            ->when($statuses !== null, fn (Builder $query): Builder => $query->whereIn('status', $statuses))
            ->when(SupportTicketKind::tryFrom((string) $kind) !== null, fn (Builder $query): Builder => $query->where('kind', $kind))
            ->when($businessId !== null, fn (Builder $query): Builder => $query->where('business_id', $businessId))
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
            default => null,
        };
    }

    /** Waited on us for longer than she allows: the one the queue paints red. */
    public function isOverdue(): bool
    {
        $since = $this->waitingSince();

        return $this->status->isOurs()
            && $since !== null
            && $since->diffInHours(now()) >= (int) config('atendia.support.overdue_hours');
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
