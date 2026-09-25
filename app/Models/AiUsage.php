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
#[Fillable(['business_id', 'kind', 'model', 'input_tokens', 'cached_tokens', 'output_tokens'])]
class AiUsage extends Model
{
    use BelongsToBusiness;

    public const string EMBEDDINGS = 'embeddings';

    public const string TRANSCRIPTION = 'transcription';

    /** The owner's panel assistant: agent rows are keyed by class basename. */
    public const string ASK_ATENDIA = 'AskAtendia';

    /**
     * The month's token totals per business and kind: the meter's raw rows.
     *
     * @return Collection<int, object{business_id: ?int, kind: string, calls: int, input_tokens: int, cached_tokens: int, output_tokens: int}>
     */
    public static function monthlyTotals(CarbonInterface $month): Collection
    {
        return self::query()
            ->selectRaw('business_id, kind, count(*) as calls, sum(input_tokens) as input_tokens, sum(cached_tokens) as cached_tokens, sum(output_tokens) as output_tokens')
            ->whereBetween('created_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->groupBy('business_id', 'kind')
            ->get()
            ->map(fn (self $row): object => (object) [
                'business_id' => $row->business_id !== null ? (int) $row->business_id : null,
                'kind' => (string) $row->kind,
                'calls' => (int) $row->getAttribute('calls'),
                'input_tokens' => (int) $row->input_tokens,
                'cached_tokens' => (int) $row->cached_tokens,
                'output_tokens' => (int) $row->output_tokens,
            ])
            ->toBase();
    }
}
