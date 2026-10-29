<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToBusiness;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * One AI call, metered. The tenant stamp comes from the trait: whichever
 * business the call ran for is the one that pays for it.
 */
#[Fillable(['business_id', 'kind', 'model', 'connection_key', 'input_tokens', 'cached_tokens', 'output_tokens'])]
class AiUsage extends Model
{
    use BelongsToBusiness;

    public const string EMBEDDINGS = 'embeddings';

    public const string TRANSCRIPTION = 'transcription';

    /** The owner's panel assistant: agent rows are keyed by class basename. */
    public const string ASK_ATENDIA = 'AskAtendia';

    /** The admin's panel assistant: it belongs to no business, so its rows carry none. */
    public const string ASK_ADMIN = 'AskAdmin';

    /** Questions the admin side asked today, on the platform's own clock: what the daily ceiling counts. */
    public static function adminQuestionsToday(): int
    {
        return self::query()
            ->whereNull('business_id')
            ->where('kind', self::ASK_ADMIN)
            ->where('created_at', '>=', now(Company::localTimezone())->startOfDay()->utc())
            ->count();
    }

    /**
     * The real businesses that asked the AI the most between two moments (up
     * to now when `$until` is null), most first. Calls with no business (the
     * platform's own) and demos never rank.
     *
     * @return Collection<int, object{business_id: int, total: int}>
     */
    public static function callsByBusiness(CarbonInterface $since, int $limit, ?CarbonInterface $until = null): Collection
    {
        return self::query()
            ->selectRaw('business_id, count(*) as total')
            ->whereNotNull('business_id')
            ->where('created_at', '>=', $since)
            ->when($until !== null, fn ($query) => $query->where('created_at', '<', $until))
            ->groupBy('business_id')
            ->orderByDesc('total')
            ->orderBy('business_id')
            ->limit($limit)
            ->get()
            ->map(fn (self $row): object => (object) [
                'business_id' => (int) $row->business_id,
                'total' => (int) $row->getAttribute('total'),
            ])
            ->toBase();
    }

    /**
     * The same totals as `monthlyTotals`, but for a run of months and with the
     * month each row belongs to. One query: a trend of twelve months read one
     * month at a time is twelve round trips on every page load.
     *
     * @return Collection<int, object{month: string, business_id: ?int, kind: string, model: ?string, calls: int, input_tokens: int, cached_tokens: int, output_tokens: int}>
     */
    public static function trailingTotals(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return self::query()
            ->selectRaw("to_char(date_trunc('month', created_at), 'YYYY-MM') as month, business_id, kind, model, count(*) as calls, sum(input_tokens) as input_tokens, sum(cached_tokens) as cached_tokens, sum(output_tokens) as output_tokens")
            ->whereBetween('created_at', [$from->copy()->startOfMonth(), $to->copy()->endOfMonth()])
            ->groupByRaw("date_trunc('month', created_at), business_id, kind, model")
            ->get()
            ->map(fn (self $row): object => (object) [
                'month' => (string) $row->getAttribute('month'),
                'business_id' => $row->business_id !== null ? (int) $row->business_id : null,
                'kind' => (string) $row->kind,
                'model' => $row->model,
                'calls' => (int) $row->getAttribute('calls'),
                'input_tokens' => (int) $row->input_tokens,
                'cached_tokens' => (int) $row->cached_tokens,
                'output_tokens' => (int) $row->output_tokens,
            ])
            ->toBase();
    }

    /**
     * The month's token totals per business and kind: the meter's raw rows.
     *
     * @return Collection<int, object{business_id: ?int, kind: string, calls: int, input_tokens: int, cached_tokens: int, output_tokens: int}>
     */
    public static function monthlyTotals(CarbonInterface $month): Collection
    {
        // Grouped by MODEL too: the price belongs to the model that answered,
        // or a model change revalues every past call at the new rate. And by
        // key, so the spend of each OpenAI key can be told apart.
        return self::query()
            ->selectRaw('business_id, kind, model, connection_key, count(*) as calls, sum(input_tokens) as input_tokens, sum(cached_tokens) as cached_tokens, sum(output_tokens) as output_tokens')
            ->whereBetween('created_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->groupBy('business_id', 'kind', 'model', 'connection_key')
            ->get()
            ->map(fn (self $row): object => (object) [
                'business_id' => $row->business_id !== null ? (int) $row->business_id : null,
                'kind' => (string) $row->kind,
                'model' => $row->model,
                'connection_key' => $row->connection_key,
                'calls' => (int) $row->getAttribute('calls'),
                'input_tokens' => (int) $row->input_tokens,
                'cached_tokens' => (int) $row->cached_tokens,
                'output_tokens' => (int) $row->output_tokens,
            ])
            ->toBase();
    }
}
