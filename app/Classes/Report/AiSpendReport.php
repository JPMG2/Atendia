<?php

declare(strict_types=1);

namespace App\Classes\Report;

use App\Classes\Main\AiSpend;
use App\Dto\ReportDto;
use App\Interfaces\Main\Report;
use App\Models\User;
use Illuminate\Support\Carbon;

/** What each business consumed and cost in a month (admin), for the accountant. */
class AiSpendReport implements Report
{
    public function authorize(User $user): bool
    {
        return $user->can('access-admin-panel');
    }

    public ReportDto $document {
        get {
            $month = $this->month();
            $spend = AiSpend::of($month);

            $rows = $spend->board
                ->map(fn (object $row): array => [
                    $row->name,
                    $row->totals->calls,
                    $row->threads,
                    $row->messages,
                    $row->totals->input,
                    $row->totals->cached,
                    $row->totals->output,
                    $this->amount($row->totals->cost),
                    $this->amount($row->perThread),
                    $row->totals->unpriced > 0 ? $row->totals->unpriced : '',
                ])
                ->values()
                ->all();

            return new ReportDto(
                title: __('reports.ai_spend.title'),
                filename: __('reports.ai_spend.filename').'-'.$month->format('Y-m'),
                columns: [
                    __('admin.ai_usage.business'),
                    __('admin.ai_usage.calls'),
                    __('admin.ai_usage.threads'),
                    __('admin.ai_usage.messages'),
                    __('admin.ai_usage.tokens_in'),
                    __('admin.ai_usage.tokens_cached'),
                    __('admin.ai_usage.tokens_out'),
                    __('reports.ai_spend.cost'),
                    __('reports.ai_spend.per_thread'),
                    __('reports.ai_spend.unpriced'),
                ],
                rows: $rows,
                subtitle: ucfirst($month->translatedFormat('F Y')),
            );
        }
    }

    /** The month on screen when the button was pressed, or this one. */
    private function month(): Carbon
    {
        $asked = (string) request()->query('mes', '');

        return rescue(
            fn (): Carbon => Carbon::createFromFormat('Y-m', $asked)->startOfMonth(),
            fn (): Carbon => now()->startOfMonth(),
            report: false,
        );
    }

    /** An unknown amount stays empty: a zero in a spreadsheet gets added up. */
    private function amount(?float $usd): string
    {
        return $usd === null ? '' : number_format($usd, 4, ',', '');
    }
}
