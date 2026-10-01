<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SupportTicketKind;
use App\Enums\SupportTicketStatus;
use App\Traits\BelongsToBusiness;
use App\Traits\SearchesText;
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
#[Fillable(['business_id', 'user_id', 'code', 'kind', 'status', 'screen', 'body', 'attachment_path', 'context', 'reply', 'answered_at', 'resolved_at'])]
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
     * business and the person already loaded.
     *
     * @return EloquentCollection<int, static>
     */
    public static function inbox(bool $onlyOpen, int $limit = 100): EloquentCollection
    {
        return static::query()
            ->with(['business:id,name', 'user:id,name'])
            ->when($onlyOpen, fn (Builder $query): Builder => $query->whereIn(
                'status',
                collect(SupportTicketStatus::cases())
                    ->filter(fn (SupportTicketStatus $case): bool => $case->isOpen())
                    ->map(fn (SupportTicketStatus $case): string => $case->value)
                    ->all(),
            ))
            ->byArrival()
            ->limit($limit)
            ->get();
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

    /** The first line, for a list that shows one row per ticket. */
    public function excerpt(int $length = 90): string
    {
        return Str::limit(trim((string) preg_replace('/\s+/', ' ', $this->body)), $length);
    }
}
