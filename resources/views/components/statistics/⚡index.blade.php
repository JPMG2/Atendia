<?php

use App\Classes\Main\Client;
use App\Classes\Main\Plan;
use App\Classes\Main\Statistics;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * "Mis estadísticas" — the GBP "Performance" mold: every block hands the
 * owner its READING, not homework. Depth is a plan dial (counts → patterns
 * → trends) and locked blocks stay visible with their padlock.
 */
new class extends Component
{
    #[Computed]
    public function stats(): ?Statistics
    {
        return Client::for(Auth::user())->statistics;
    }

    #[Computed]
    public function plan(): Plan
    {
        return Auth::user()?->business?->plan() ?? Plan::named(null);
    }

    #[Computed]
    public function kpis(): array
    {
        return $this->stats?->monthKpis() ?? [];
    }

    #[Computed]
    public function previousKpis(): array
    {
        return $this->stats?->monthKpis(now()->toImmutable()->subMonthNoOverflow()) ?? [];
    }

    /** GBP gives everyone the arrow: a number without its delta is dead. */
    public function deltaFor(string $key): array
    {
        $current = (int) ($this->kpis[$key] ?? 0);
        $previous = (int) ($this->previousKpis[$key] ?? 0);

        if ($previous === 0) {
            return ['delta' => null, 'trend' => 'flat'];
        }

        $percent = (int) round(($current - $previous) / $previous * 100);

        return [
            'delta' => ($percent >= 0 ? '+' : '').$percent.'%',
            'trend' => $percent > 0 ? 'up' : ($percent < 0 ? 'down' : 'flat'),
        ];
    }

    /** The tab title comes from translations; a PHP attribute cannot call __(). */
    public function render(): View
    {
        return $this->view()->title(__('statistics.title'));
    }
};
?>

<div>
    <div class="page-head">
        <div>
            <h1 class="page-head-title">{{ __('statistics.title') }}</h1>
            <p class="page-head-sub">{{ __('statistics.sub') }}</p>
        </div>
    </div>

    @if ($this->stats !== null)
        @php($sinceDayOne = $this->stats->sinceDayOne)

        {{-- The retention counter: what Atendia did since the day they hired it. --}}
        @if ($sinceDayOne['since'] !== null)
            <div class="stats-hero">
                <x-icon name="bot" :size="20" />
                <p>
                    {!! __('statistics.since', [
                        'date' => $sinceDayOne['since']->translatedFormat('j \d\e F'),
                        'questions' => '<b>'.number_format($sinceDayOne['questions'], 0, ',', '.').'</b>',
                        'conversations' => '<b>'.number_format($sinceDayOne['conversations'], 0, ',', '.').'</b>',
                    ]) !!}
                </p>
            </div>
        @endif

        <div class="stat-grid">
            @foreach ([
                'conversations' => 'message-circle',
                'new_contacts' => 'users',
                'questions' => 'bot',
                'audio_minutes' => 'zap',
            ] as $key => $icon)
                @php($delta = $this->deltaFor($key))
                <x-ui.stat-card
                    :label="__('statistics.kpis.'.$key)"
                    :value="number_format((int) ($this->kpis[$key] ?? 0), 0, ',', '.')"
                    :delta="$delta['delta']"
                    :trend="$delta['trend']"
                    :icon="$icon"
                    tint="brand"
                />
            @endforeach
        </div>

        <div class="duo-grid mt-4">
            {{-- Patterns: what do they ask me? --}}
            @if ($this->plan->statisticsAtLeast('patterns'))
                <x-ui.card class="p-6">
                    <h2 class="block-title">{{ __('statistics.daily.title') }}</h2>
                    @php($bestDay = $this->stats->bestDay())
                    @if ($bestDay !== null)
                        <p class="stats-insight">
                            {{ __('statistics.daily.insight', ['day' => $bestDay['label'], 'count' => $bestDay['count']]) }}
                        </p>
                        <x-statistics.bar-chart
                            :series="$this->stats->dailySeries()"
                            :labelEvery="7"
                            :tableLabel="__('statistics.daily.title')"
                        />
                    @else
                        <p class="stats-empty">{{ __('statistics.gathering') }}</p>
                    @endif
                </x-ui.card>

                <x-ui.card class="p-6">
                    <h2 class="block-title">{{ __('statistics.top.title') }}</h2>
                    @php($topAsked = $this->stats->topAsked())
                    @if ($topAsked !== [])
                        @php($topMax = max(array_column($topAsked, 'count')))
                        <ul class="stats-top">
                            @foreach ($topAsked as $topic)
                                <li>
                                    <div class="stats-top-row">
                                        <span class="stats-top-text">{{ $topic['sample'] }}</span>
                                        <b class="font-mono">{{ $topic['count'] }}</b>
                                    </div>
                                    <div class="plan-meter-track">
                                        <div class="plan-meter-fill" style="width: {{ (int) round($topic['count'] / $topMax * 100) }}%"></div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="stats-empty">{{ __('statistics.gathering') }}</p>
                    @endif
                </x-ui.card>
            @else
                <x-statistics.locked-card :title="__('statistics.daily.title')" plan="negocio" />
                <x-statistics.locked-card :title="__('statistics.top.title')" plan="negocio" />
            @endif

            {{-- Trends: where do I grow? --}}
            @if ($this->plan->statisticsAtLeast('trends'))
                <x-ui.card class="p-6">
                    <h2 class="block-title">{{ __('statistics.hours.title') }}</h2>
                    @php($peakHours = $this->stats->peakHours)
                    @if ($peakHours['window'] !== null)
                        <p class="stats-insight">
                            {{ __('statistics.hours.insight', ['share' => $peakHours['window']['share'], 'from' => $peakHours['window']['from'], 'to' => $peakHours['window']['to']]) }}
                        </p>
                        <x-statistics.bar-chart
                            :series="collect($peakHours['counts'])->map(fn (int $count, int $hour): array => ['label' => $hour.'h', 'count' => $count])->all()"
                            :labelEvery="6"
                            :tableLabel="__('statistics.hours.title')"
                        />
                    @else
                        <p class="stats-empty">{{ __('statistics.gathering') }}</p>
                    @endif
                </x-ui.card>

                <x-ui.card class="p-6">
                    <h2 class="block-title">{{ __('statistics.trend.title') }}</h2>
                    @php($trend = $this->stats->monthlyTrend())
                    @php($lastMonth = $trend[count($trend) - 2]['count'] ?? 0)
                    @if ($lastMonth > 0)
                        <p class="stats-insight">
                            {{ __('statistics.trend.insight', ['count' => end($trend)['count'], 'previous' => $lastMonth]) }}
                        </p>
                    @endif
                    <x-statistics.bar-chart
                        :series="$trend"
                        :labelEvery="1"
                        :tableLabel="__('statistics.trend.title')"
                    />
                </x-ui.card>
            @else
                <x-statistics.locked-card :title="__('statistics.hours.title')" plan="premium" />
                <x-statistics.locked-card :title="__('statistics.trend.title')" plan="premium" />
            @endif
        </div>

        {{-- The premium jewel: demand the catalog is letting walk away. --}}
        @if ($this->plan->statisticsAtLeast('trends'))
            @php($gaps = $this->stats->catalogGaps())
            @if ($gaps !== [])
                <x-ui.card class="stats-gaps p-6">
                    <h2 class="block-title">{{ __('statistics.gaps.title') }}</h2>
                    <ul class="stats-top">
                        @foreach ($gaps as $gap)
                            <li class="stats-top-row">
                                <span class="stats-top-text">
                                    {{ __('statistics.gaps.line', ['count' => $gap['count'], 'sample' => $gap['sample']]) }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif
        @else
            <x-statistics.locked-card :title="__('statistics.gaps.title')" plan="premium" />
        @endif
    @endif
</div>
