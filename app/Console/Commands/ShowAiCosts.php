<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\ConversationMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * The number the pricing session waits for: what each business's assistant
 * really costs per month, from measured tokens and audio — not estimates.
 * Runs with no tenant on purpose: it is the owner's admin report.
 */
class ShowAiCosts extends Command
{
    protected $signature = 'atendia:ai-costs {--month= : Month to report, YYYY-MM (defaults to the current one)}';

    protected $description = 'Show per-business AI usage (tokens, audio) and its estimated USD cost';

    public function handle(): int
    {
        $month = $this->option('month') !== null
            ? Carbon::createFromFormat('Y-m', (string) $this->option('month'))->startOfMonth()
            : now()->startOfMonth();

        $rows = ConversationMessage::query()
            ->selectRaw(<<<'SQL'
                business_id,
                count(distinct conversation_id) as threads,
                count(*) filter (where direction = 'out') as exchanges,
                coalesce(sum(prompt_tokens), 0) as prompt_tokens,
                coalesce(sum(completion_tokens), 0) as completion_tokens,
                coalesce(sum(audio_seconds), 0) as audio_seconds
                SQL)
            ->whereBetween('created_at', [$month, $month->copy()->endOfMonth()])
            ->groupBy('business_id')
            ->get();

        if ($rows->isEmpty()) {
            $this->info("No AI usage recorded for {$month->format('Y-m')}.");

            return self::SUCCESS;
        }

        $names = Business::query()->whereIn('id', $rows->pluck('business_id'))->pluck('name', 'id');

        $this->table(
            ['Negocio', 'Hilos', 'Intercambios', 'Tokens in', 'Tokens out', 'Audio (min)', 'Costo (USD)'],
            $rows->map(fn ($row): array => [
                $names[$row->business_id] ?? "#{$row->business_id}",
                (string) $row->threads,
                (string) $row->exchanges,
                number_format((int) $row->prompt_tokens),
                number_format((int) $row->completion_tokens),
                number_format($row->audio_seconds / 60, 1),
                $this->cost($row) ?? 'configurar tarifas',
            ])->all(),
        );

        return self::SUCCESS;
    }

    private function cost(object $row): ?string
    {
        $in = config('atendia.ai_rates.prompt_per_million');
        $out = config('atendia.ai_rates.completion_per_million');

        if ($in === null || $out === null) {
            return null;
        }

        $usd = ((int) $row->prompt_tokens / 1_000_000) * (float) $in
            + ((int) $row->completion_tokens / 1_000_000) * (float) $out
            + ($row->audio_seconds / 60) * (float) config('atendia.ai_rates.audio_per_minute', 0);

        return '$'.number_format($usd, 2);
    }
}
