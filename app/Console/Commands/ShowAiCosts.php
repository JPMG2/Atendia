<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AiUsage;
use App\Models\Business;
use App\Models\ConversationMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The per-client meter the pricing session waits for: each business's
 * volume and what its AI really cost, from the metered calls of every
 * kind — not estimates. Runs with no tenant on purpose: it is the admin's.
 */
class ShowAiCosts extends Command
{
    protected $signature = 'atendia:ai-costs {--month= : Month to report, YYYY-MM (defaults to the current one)}';

    protected $description = 'Show per-business AI usage (volume, tokens, audio) and its estimated USD cost';

    public function handle(): int
    {
        $month = $this->option('month') !== null
            ? Carbon::createFromFormat('Y-m', (string) $this->option('month'))->startOfMonth()
            : now()->startOfMonth();

        $usage = AiUsage::monthlyTotals($month);
        $volume = ConversationMessage::monthlyVolume($month)->keyBy('business_id');

        if ($usage->isEmpty() && $volume->isEmpty()) {
            $this->info("No AI usage recorded for {$month->format('Y-m')}.");

            return self::SUCCESS;
        }

        $ids = $usage->pluck('business_id')->merge($volume->keys())->unique()->values();
        $names = Business::query()->whereIn('id', $ids->filter())->pluck('name', 'id');

        $this->table(
            ['Negocio', 'Hilos', 'Mensajes', 'Llamadas IA', 'Tokens in', 'Caché', 'Tokens out', 'Audio (min)', 'Costo (USD)'],
            $ids->map(function (?int $id) use ($usage, $volume, $names): array {
                $calls = $usage->where('business_id', $id);
                $traffic = $volume->get($id);
                $audioSeconds = (int) ($traffic->audio_seconds ?? 0);

                return [
                    $id === null ? 'Plataforma' : ($names[$id] ?? "#{$id}"),
                    (string) ($traffic->threads ?? 0),
                    (string) ($traffic->messages ?? 0),
                    number_format($calls->sum('calls')),
                    number_format($calls->sum('input_tokens')),
                    number_format($calls->sum('cached_tokens')),
                    number_format($calls->sum('output_tokens')),
                    number_format($audioSeconds / 60, 1),
                    $this->money($this->cost($calls, $audioSeconds)),
                ];
            })->all(),
        );

        $this->table(
            ['Tipo', 'Llamadas', 'Tokens in', 'Caché', 'Tokens out', 'Costo (USD)'],
            $usage->groupBy('kind')->map(fn (Collection $calls, string $kind): array => [
                $kind,
                number_format($calls->sum('calls')),
                number_format($calls->sum('input_tokens')),
                number_format($calls->sum('cached_tokens')),
                number_format($calls->sum('output_tokens')),
                $this->money($this->cost($calls, 0)),
            ])->sortKeys()->values()->all(),
        );

        return self::SUCCESS;
    }

    /**
     * Transcription is priced by the audio minute, so its tokens never add
     * cost here; cached input falls back to the full rate until it is set.
     *
     * @param  Collection<int, object>  $calls
     */
    private function cost(Collection $calls, int $audioSeconds): ?float
    {
        $rates = config('atendia.ai_rates');

        if ($rates['prompt_per_million'] === null || $rates['completion_per_million'] === null) {
            return null;
        }

        $cached = (float) ($rates['cached_per_million'] ?? $rates['prompt_per_million']);

        return $calls->sum(fn (object $row): float => match ($row->kind) {
            AiUsage::EMBEDDINGS => $row->input_tokens * (float) $rates['embedding_per_million'],
            AiUsage::TRANSCRIPTION => 0.0,
            default => $row->input_tokens * (float) $rates['prompt_per_million']
                + $row->cached_tokens * $cached
                + $row->output_tokens * (float) $rates['completion_per_million'],
        }) / 1_000_000 + ($audioSeconds / 60) * (float) $rates['audio_per_minute'];
    }

    private function money(?float $usd): string
    {
        return $usd === null ? 'configurar tarifas' : '$'.number_format($usd, 2);
    }
}
