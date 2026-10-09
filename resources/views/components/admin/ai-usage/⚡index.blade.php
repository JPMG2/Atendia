<?php

use App\Classes\Main\AiSpend;
use App\Classes\Main\AiTrend;
use App\Models\AiConnection;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * What each business consumed in a month, and what it cost her.
 *
 * Every figure is metered, never estimated, and a month whose model has no
 * published price says so instead of showing a zero that reads as "free".
 */
new class extends Component
{
    /** The month on screen, as Y-m. */
    public string $month = '';

    public function mount(): void
    {
        $this->month = now()->format('Y-m');
    }

    /**
     * The picker can be cleared, and a month that is not one is not an empty
     * screen but a crash: the figures fall back to the month she is in.
     */
    private function selected(): Carbon
    {
        try {
            return Carbon::createFromFormat('Y-m', $this->month)->startOfMonth();
        } catch (InvalidFormatException) {
            return Carbon::now()->startOfMonth();
        }
    }

    #[Computed]
    public function spend(): AiSpend
    {
        return AiSpend::of($this->selected());
    }

    /** Whether the month on screen is the running one: the only one fixed costs can be netted against. */
    #[Computed]
    public function isCurrentMonth(): bool
    {
        return $this->selected()->isSameMonth(now());
    }

    #[Computed]
    public function previous(): AiSpend
    {
        return AiSpend::of($this->selected()->subMonth());
    }

    /**
     * The twelve months behind the month on screen, read in one query: a cost
     * on its own cannot say whether it is where this business always sits.
     */
    #[Computed]
    public function trend(): AiTrend
    {
        return AiTrend::upTo($this->selected());
    }

    /**
     * The last twelve months to choose from: the meter has no older data than
     * the day it was installed, and a year is as far back as she compares.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function months(): array
    {
        return collect(range(0, 11))
            ->mapWithKeys(function (int $back): array {
                $month = now()->startOfMonth()->subMonths($back);

                return [$month->format('Y-m') => ucfirst($month->translatedFormat('F Y'))];
            })
            ->all();
    }

    /**
     * What each key is called in the AI screen; a key nobody named shows as itself.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function keyNames(): array
    {
        return AiConnection::labels();
    }

    /**
     * The column sums, so the last row answers "and in total?" instead of
     * leaving her to add up a column by eye.
     *
     * @return object{threads: int, messages: int, calls: int, tokens: int, cached: float|null, cost: float|null}
     */
    #[Computed]
    public function totals(): object
    {
        $board = $this->spend->board;
        $bill = $this->spend->bill;

        return (object) [
            'threads' => (int) $board->sum('threads'),
            'messages' => (int) $board->sum('messages'),
            'calls' => $bill->calls,
            'tokens' => $bill->input + $bill->cached + $bill->output,
            'cached' => $this->cachedShare($bill->input, $bill->cached),
            'cost' => $bill->cost,
        ];
    }

    /**
     * How much of what was read came back from the cache: the number that says
     * whether the prompts are ordered so the cheap rate applies. Nothing read
     * is no share at all, never zero.
     */
    public function cachedShare(int $input, int $cached): ?float
    {
        $read = $input + $cached;

        return $read > 0 ? $cached / $read : null;
    }

    /** A whole number, written the way this app writes every other one. */
    public function count(int $amount): string
    {
        return number_format($amount, 0, ',', '.');
    }

    /** An amount nobody can read as something it is not. */
    public function money(?float $usd): string
    {
        if ($usd === null) {
            return '—';
        }

        // The sign goes before the currency: "$-1,00" reads as a typo.
        if ($usd < 0) {
            return '-'.$this->money(abs($usd));
        }

        if ($usd > 0 && $usd < 0.005) {
            return '< $0,01';
        }

        return '$'.number_format($usd, 2, ',', '.');
    }

    /** A share, or the dash that says it could not be worked out. */
    public function percent(?float $share): string
    {
        return $share === null ? '—' : number_format($share * 100, 0, ',', '.').'%';
    }

    /** The tab is copy: a PHP attribute cannot call __(). */
    public function render(): View
    {
        return $this->view()->title(__('admin.ai_usage.title'));
    }
};
?>

<div>
    <x-ui.page-head :title="__('admin.ai_usage.title')" :sub="__('admin.ai_usage.sub')">
        {{-- One box, or `space-between` in the head would scatter the three
        buttons across the width instead of grouping them. --}}
        <div class="flex flex-wrap items-center gap-2">
            <x-ui.export-button report="ai-spend" format="pdf" size="sm" :params="['mes' => $month]" />
            <x-ui.export-button report="ai-spend" format="xlsx" size="sm" :params="['mes' => $month]" />
            <x-ui.export-button report="ai-spend" format="csv" size="sm" :params="['mes' => $month]" />
        </div>
    </x-ui.page-head>

    <x-ui.card class="mb-3 p-5">
        <x-catalog.form-row class="aiu-toolbar">
            <x-inputsform.combobox
                size="s"
                span="short"
                :label="__('admin.ai_usage.month')"
                name="month"
                :options="$this->months"
                :value="$month"
                wire:model.live="month"
            />
        </x-catalog.form-row>

        {{-- The month in three figures, before the rows that explain it: what
        came in, what the AI took, what was left. Margin is over the AI only —
        servers and the rest of the fixed costs are not metered here. --}}
        @php($result = $this->spend->result)
        @if ($result->revenue !== null || $result->cost !== null)
            @php($before = $this->previous->result)
            <div class="stat-grid stat-grid-fill my-3">
                <x-ui.stat-card
                    :label="__('admin.ai_usage.result.revenue')"
                    :value="$this->money($result->revenue)"
                    icon="credit-card"
                    tint="info"
                >{{ $result->revenue === null ? __('admin.ai_usage.result.revenue_missing') : __('admin.ai_usage.result.revenue_hint') }}</x-ui.stat-card>

                <x-ui.stat-card
                    :label="__('admin.ai_usage.result.cost')"
                    :value="$this->money($result->cost)"
                    icon="zap"
                    tint="accent"
                >{{ $result->unpriced > 0 ? trans_choice('admin.ai_usage.no_price', $result->unpriced) : __('admin.ai_usage.result.cost_hint') }}</x-ui.stat-card>

                <x-ui.stat-card
                    :label="__('admin.ai_usage.result.margin')"
                    :value="$this->money($result->margin)"
                    :delta="$result->share === null ? null : $this->percent(abs($result->share))"
                    :trend="($result->margin ?? 0) < 0 ? 'down' : 'up'"
                    icon="bar-chart-3"
                    :tint="($result->margin ?? 0) < 0 ? 'warning' : 'brand'"
                >{{ $before->margin === null ? __('admin.ai_usage.result.first_month') : __('admin.ai_usage.result.margin_before', ['amount' => $this->money($before->margin)]) }}</x-ui.stat-card>

                {{-- The net, or the door to load what it is missing: a tile that
                cannot be filled in yet is the way to the setting, not a zero. --}}
                @if ($result->fixed !== null || $this->isCurrentMonth)
                    <x-ui.stat-card
                        :label="__('admin.ai_usage.result.net')"
                        :value="$this->money($result->net)"
                        icon="receipt"
                        :tint="($result->net ?? 0) < 0 ? 'warning' : 'brand'"
                        :href="$result->fixed === null ? route('admin.settings') : null"
                    >{{ $result->fixed === null ? __('admin.ai_usage.result.net_missing') : __('admin.ai_usage.result.net_hint', ['amount' => $this->money($result->fixed)]) }}</x-ui.stat-card>
                @endif
            </div>
        @endif

        @if ($this->spend->board->isEmpty())
            <x-ui.empty-state
                compact
                icon="bar-chart-3"
                :title="__('admin.ai_usage.empty_title')"
                :body="__('admin.ai_usage.empty')"
            />
        @else
            {{-- A real table, not rows of floating text: one business's figure
            only means something read down the column of all of them, so every
            number is right-aligned and monospaced. --}}
            <div class="pay-table-wrap">
                <table class="pay-table" data-sortable>
                    <thead>
                        <tr>
                            <th>{{ __('admin.ai_usage.business') }}</th>
                            <th class="is-num">{{ __('admin.ai_usage.threads') }}</th>
                            <th class="is-num">{{ __('admin.ai_usage.messages') }}</th>
                            <th class="is-num">{{ __('admin.ai_usage.calls') }}</th>
                            <th class="is-num">{{ __('admin.ai_usage.tokens') }}</th>
                            <th class="is-num">{{ __('admin.ai_usage.cached') }}</th>
                            <th class="is-num">{{ __('admin.ai_usage.cost') }}</th>
                            <th class="is-num">{{ __('admin.ai_usage.per_thread') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->spend->board as $row)
                            {{-- The row that is over its plan's threshold is painted,
                            not only tagged: a tag is read, a row is seen. --}}
                            <tr wire:key="spend-{{ $row->id ?? 'platform' }}" @class(['is-over' => $row->isOverAlert])>
                                <td class="is-name is-key" data-label="{{ __('admin.ai_usage.business') }}">
                                    <span class="aiu-name">
                                        @if ($row->id === null)
                                            {{ $row->name }}
                                        @else
                                            <a class="row-link" data-row-action wire:navigate href="{{ route('admin.businesses', ['negocio' => $row->id]) }}">{{ $row->name }}</a>
                                        @endif
                                        @if ($row->isOverAlert)
                                            <span class="status-tag is-danger">
                                                {{ __('admin.ai_usage.over_plan', ['share' => round($row->share * 100)]) }}
                                            </span>
                                        @endif
                                    </span>
                                    @if ($row->id === null)
                                        <span class="aiu-note">{{ __('admin.ai_usage.platform_hint') }}</span>
                                    @endif
                                    {{-- The range is the same on every row, so it is said
                                    once under the table and not four times here. --}}
                                    <span class="aiu-trend">
                                        <x-ui.sparkline
                                            :values="$this->trend->seriesFor($row->id)"
                                            :label="__('admin.ai_usage.trend_label', ['name' => $row->name])"
                                        />
                                    </span>
                                </td>
                                <td class="is-num font-mono" data-label="{{ __('admin.ai_usage.threads') }}">{{ $this->count($row->threads) }}</td>
                                <td class="is-num font-mono" data-label="{{ __('admin.ai_usage.messages') }}">{{ $this->count($row->messages) }}</td>
                                <td class="is-num font-mono" data-label="{{ __('admin.ai_usage.calls') }}">{{ $this->count($row->totals->calls) }}</td>
                                <td class="is-num font-mono" data-label="{{ __('admin.ai_usage.tokens') }}">
                                    {{ $this->count($row->totals->input + $row->totals->cached + $row->totals->output) }}
                                </td>
                                <td class="is-num font-mono" data-label="{{ __('admin.ai_usage.cached') }}">
                                    {{ $this->percent($this->cachedShare($row->totals->input, $row->totals->cached)) }}
                                    @if ($row->saved > 0)
                                        {{-- A share is not an argument; the money it took
                                        off the bill is what makes the cache worth keeping. --}}
                                        <span class="aiu-note">{{ __('admin.ai_usage.saved', ['amount' => $this->money($row->saved)]) }}</span>
                                    @endif
                                </td>
                                <td class="is-num font-mono aiu-cost-now" data-label="{{ __('admin.ai_usage.cost') }}">
                                    {{ $this->money($row->totals->cost) }}
                                    @if ($row->totals->unpriced > 0)
                                        {{-- The count lives in the note under the table: in the
                                        cell it only has to say the figure is short. --}}
                                        <span class="aiu-note">{{ __('admin.ai_usage.unpriced') }}</span>
                                    @endif
                                    {{-- What this business left after its AI: a loss is a tag
                                    and not a note, because it is the line she acts on. A trial
                                    has no revenue to subtract from: its cost is the price of winning it. --}}
                                    @if ($row->standing === 'paying' && $row->margin !== null)
                                        @if ($row->margin < 0)
                                            <span class="status-tag is-danger">{{ __('admin.ai_usage.loses', ['amount' => $this->money(abs($row->margin))]) }}</span>
                                        @else
                                            <span class="aiu-note">{{ __('admin.ai_usage.leaves', ['amount' => $this->money($row->margin)]) }}</span>
                                        @endif
                                    @elseif ($row->standing === 'trial')
                                        <span class="aiu-note">{{ __('admin.ai_usage.on_trial') }}</span>
                                    @endif
                                </td>
                                <td class="is-num font-mono" data-label="{{ __('admin.ai_usage.per_thread') }}">{{ $this->money($row->perThread) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th scope="row">{{ __('admin.ai_usage.month_total') }}</th>
                            <td class="is-num font-mono" data-label="{{ __('admin.ai_usage.threads') }}">{{ $this->count($this->totals->threads) }}</td>
                            <td class="is-num font-mono" data-label="{{ __('admin.ai_usage.messages') }}">{{ $this->count($this->totals->messages) }}</td>
                            <td class="is-num font-mono" data-label="{{ __('admin.ai_usage.calls') }}">{{ $this->count($this->totals->calls) }}</td>
                            <td class="is-num font-mono" data-label="{{ __('admin.ai_usage.tokens') }}">{{ $this->count($this->totals->tokens) }}</td>
                            <td class="is-num font-mono" data-label="{{ __('admin.ai_usage.cached') }}">
                                {{ $this->percent($this->totals->cached) }}
                                @if ($this->spend->saved > 0)
                                    <span class="aiu-note">{{ __('admin.ai_usage.saved', ['amount' => $this->money($this->spend->saved)]) }}</span>
                                @endif
                            </td>
                            <td class="is-num font-mono aiu-cost-now" data-label="{{ __('admin.ai_usage.cost') }}">{{ $this->money($this->totals->cost) }}</td>
                            <td class="is-num font-mono" data-label="{{ __('admin.ai_usage.per_thread') }}">—</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <p class="aiu-foot">
                <span>{{ __('admin.ai_usage.measured', ['when' => now()->format('d/m/Y H:i')]) }}</span>
                <span>{{ __('admin.ai_usage.trend_since', ['month' => $this->trend->span, 'count' => $this->trend->length]) }}</span>
                @if ($this->spend->saved > 0)
                    <span>{{ __('admin.ai_usage.saved_hint') }}</span>
                @endif
                @if ($this->spend->board->contains('standing', 'trial'))
                    <span>{{ __('admin.ai_usage.trial_hint') }}</span>
                @endif
                <span>{{ __('admin.ai_usage.previous_is', ['amount' => $this->money($this->previous->bill->cost)]) }}</span>
                @if ($this->spend->bill->unpriced > 0)
                    <span class="aiu-warn">{{ trans_choice('admin.ai_usage.no_price', $this->spend->bill->unpriced) }}</span>
                @endif
            </p>
        @endif
    </x-ui.card>

    <x-ui.card class="p-5">
        <h2 class="aiu-section">{{ __('admin.ai_usage.by_kind') }}</h2>

        @if ($this->spend->byKind->isEmpty())
            <x-ui.empty-state
                compact
                icon="zap"
                :title="__('admin.ai_usage.empty_title')"
                :body="__('admin.ai_usage.empty')"
            />
        @else
            <div class="pay-table-wrap">
                <table class="pay-table" data-sortable>
                    <thead>
                        <tr>
                            <th>{{ __('admin.ai_usage.kind') }}</th>
                            <th class="is-num">{{ __('admin.ai_usage.calls') }}</th>
                            <th class="is-num">{{ __('admin.ai_usage.tokens_in') }}</th>
                            <th class="is-num">{{ __('admin.ai_usage.tokens_cached') }}</th>
                            <th class="is-num">{{ __('admin.ai_usage.tokens_out') }}</th>
                            <th class="is-num">{{ __('admin.ai_usage.cost') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->spend->byKind as $kind)
                            <tr wire:key="kind-{{ $kind->kind }}">
                                <td class="font-mono" data-label="{{ __('admin.ai_usage.kind') }}">{{ $kind->kind }}</td>
                                <td class="is-num font-mono" data-label="{{ __('admin.ai_usage.calls') }}">{{ $this->count($kind->calls) }}</td>
                                <td class="is-num font-mono" data-label="{{ __('admin.ai_usage.tokens_in') }}">{{ $this->count($kind->input) }}</td>
                                <td class="is-num font-mono" data-label="{{ __('admin.ai_usage.tokens_cached') }}">{{ $this->count($kind->cached) }}</td>
                                <td class="is-num font-mono" data-label="{{ __('admin.ai_usage.tokens_out') }}">{{ $this->count($kind->output) }}</td>
                                <td class="is-num font-mono aiu-cost-now" data-label="{{ __('admin.ai_usage.cost') }}">
                                    {{ $this->money($kind->cost) }}
                                    @if ($kind->unpriced > 0)
                                        <span class="aiu-note">{{ trans_choice('admin.ai_usage.no_price_short', $kind->unpriced) }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>

    {{-- The same month cut by the key it went through: two OpenAI keys split the
    spend and the rate limit, and this is where the split is read. --}}
    @if ($this->spend->byConnection->isNotEmpty())
        <x-ui.card class="mt-3 p-5">
            <h2 class="aiu-section">{{ __('admin.ai_usage.by_connection') }}</h2>

            <div class="pay-table-wrap">
                <table class="pay-table" data-sortable>
                    <thead>
                        <tr>
                            <th>{{ __('admin.ai_usage.connection') }}</th>
                            <th class="is-num">{{ __('admin.ai_usage.calls') }}</th>
                            <th class="is-num">{{ __('admin.ai_usage.tokens_in') }}</th>
                            <th class="is-num">{{ __('admin.ai_usage.tokens_cached') }}</th>
                            <th class="is-num">{{ __('admin.ai_usage.tokens_out') }}</th>
                            <th class="is-num">{{ __('admin.ai_usage.cost') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->spend->byConnection as $connection)
                            <tr wire:key="connection-{{ $connection->kind ?: 'none' }}">
                                <td class="is-name is-key" data-label="{{ __('admin.ai_usage.connection') }}">
                                    @if ($connection->kind === '')
                                        <span class="aiu-name">{{ __('admin.ai_usage.no_connection') }}</span>
                                        <span class="aiu-note">{{ __('admin.ai_usage.no_connection_hint') }}</span>
                                    @else
                                        <span class="aiu-name">{{ $this->keyNames[$connection->kind] ?? $connection->kind }}</span>
                                        <span class="aiu-note font-mono">{{ $connection->kind }}</span>
                                    @endif
                                </td>
                                <td class="is-num font-mono" data-label="{{ __('admin.ai_usage.calls') }}">{{ $this->count($connection->calls) }}</td>
                                <td class="is-num font-mono" data-label="{{ __('admin.ai_usage.tokens_in') }}">{{ $this->count($connection->input) }}</td>
                                <td class="is-num font-mono" data-label="{{ __('admin.ai_usage.tokens_cached') }}">{{ $this->count($connection->cached) }}</td>
                                <td class="is-num font-mono" data-label="{{ __('admin.ai_usage.tokens_out') }}">{{ $this->count($connection->output) }}</td>
                                <td class="is-num font-mono aiu-cost-now" data-label="{{ __('admin.ai_usage.cost') }}">
                                    {{ $this->money($connection->cost) }}
                                    @if ($connection->unpriced > 0)
                                        <span class="aiu-note">{{ trans_choice('admin.ai_usage.no_price_short', $connection->unpriced) }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <p class="aiu-foot"><span>{{ __('admin.ai_usage.by_connection_hint') }}</span></p>
        </x-ui.card>
    @endif
</div>
