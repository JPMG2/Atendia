<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Classes\Main\AiSpend;
use App\Models\Business;
use App\Models\ConversationMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

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

        $spend = AiSpend::of($month);
        $volume = ConversationMessage::monthlyVolume($month)->keyBy('business_id');

        if ($spend->isEmpty && $volume->isEmpty()) {
            $this->info("No AI usage recorded for {$month->format('Y-m')}.");

            return self::SUCCESS;
        }

        $ids = $spend->businessIds->merge($volume->keys())->unique()->values();
        $names = Business::query()->whereIn('id', $ids->filter())->pluck('name', 'id');

        $this->table(
            ['Negocio', 'Hilos', 'Mensajes', 'Llamadas IA', 'Tokens in', 'Caché', 'Tokens out', 'Audio (min)', 'Costo (USD)'],
            $ids->map(function (?int $id) use ($spend, $volume, $names): array {
                $traffic = $volume->get($id);
                $audioSeconds = (int) ($traffic->audio_seconds ?? 0);
                $totals = $spend->forBusiness($id, $audioSeconds);

                return [
                    $id === null ? 'Plataforma' : ($names[$id] ?? "#{$id}"),
                    (string) ($traffic->threads ?? 0),
                    (string) ($traffic->messages ?? 0),
                    number_format($totals->calls),
                    number_format($totals->input),
                    number_format($totals->cached),
                    number_format($totals->output),
                    number_format($audioSeconds / 60, 1),
                    $this->money($totals->cost),
                ];
            })->all(),
        );

        $this->table(
            ['Tipo', 'Llamadas', 'Tokens in', 'Caché', 'Tokens out', 'Costo (USD)'],
            $spend->byKind->map(fn (object $totals): array => [
                $totals->kind,
                number_format($totals->calls),
                number_format($totals->input),
                number_format($totals->cached),
                number_format($totals->output),
                $this->money($totals->cost),
            ])->all(),
        );

        return self::SUCCESS;
    }

    private function money(?float $usd): string
    {
        return $usd === null ? 'configurar tarifas' : '$'.number_format($usd, 2);
    }
}
