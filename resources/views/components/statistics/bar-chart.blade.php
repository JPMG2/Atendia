{{-- One bar chart for every statistics block: single jade series (magnitude
needs one hue), the peak bar at full strength with its value as the only
direct label, a tap-or-hover tip per bar (a native title never shows on a
phone), an optional dashed shadow of the previous window, and a screen-reader
table so the data never lives in color alone. --}}
@props([
    'series' => [],
    'labelEvery' => 7,
    'tableLabel' => '',
    'todayLast' => false,
])

@php
    $count = count($series);
    $hasPrevious = collect($series)->sum(fn (array $point): int => $point['previous'] ?? 0) > 0;
    $max = max(1, collect($series)->max('count'), $hasPrevious ? collect($series)->max('previous') : 0);
    $peak = collect($series)->search(fn (array $point): bool => $point['count'] === collect($series)->max('count'));
    // Each bar gets a slot of at least 1/300 of the width: with few bars
    // (six months) a narrow viewBox scaled to full width grew huge.
    $slot = max(10, 300 / max(1, $count));
    $barWidth = min($slot - 3, 24);
    $chartHeight = 96;
    $width = $count * $slot;
    $tips = collect($series)->map(fn (array $point): string => $point['label'].' · '.$point['count']
        .($hasPrevious ? ' ('.__('statistics.chart.previous', ['count' => $point['previous'] ?? 0]).')' : ''))->all();
    // With "today" as the last bar the ticks count back from it, so it always gets its label.
    $hasTick = fn (int $index): bool => $todayLast ? ($count - 1 - $index) % $labelEvery === 0 : $index % $labelEvery === 0;
@endphp

{{-- Labels are HTML laid over the SVG, not SVG text: the SVG scales with its
card, so text inside it went from 9px on a phone to 16px at 900px. --}}
<div class="stats-chart" x-data="{ active: null, tips: @js($tips) }">
    @if ($hasPrevious)
        <div class="stats-chart-legend">
            <span><i class="stats-chart-key"></i>{{ __('statistics.daily.current') }}</span>
            <span><i class="stats-chart-key is-previous"></i>{{ __('statistics.daily.previous') }}</span>
        </div>
    @endif
    <div class="stats-chart-plot" x-on:mouseleave="active = null">
        <svg
            viewBox="0 0 {{ $width }} {{ $chartHeight }}"
            role="img"
            aria-label="{{ $tableLabel }}"
            preserveAspectRatio="none"
        >
            @foreach ($series as $index => $point)
                @php
                    $height = $point['count'] > 0 ? max(3, (int) round($point['count'] / $max * $chartHeight)) : 2;
                    $x = $index * $slot + ($slot - $barWidth) / 2;
                    $isPeak = $index === $peak && $point['count'] > 0;
                @endphp
                <rect
                    x="{{ $x }}"
                    y="{{ $chartHeight - $height }}"
                    width="{{ $barWidth }}"
                    height="{{ $height }}"
                    rx="2"
                    fill="var(--brand)"
                    fill-opacity="{{ $isPeak ? '1' : ($point['count'] > 0 ? '0.45' : '0.14') }}"
                />
                @if ($hasPrevious && ($point['previous'] ?? 0) > 0)
                    @php($ghost = max(3, (int) round($point['previous'] / $max * $chartHeight)))
                    <rect
                        class="stats-chart-ghost"
                        x="{{ $x }}"
                        y="{{ $chartHeight - $ghost }}"
                        width="{{ $barWidth }}"
                        height="{{ $ghost }}"
                        rx="2"
                        vector-effect="non-scaling-stroke"
                    />
                @endif
            @endforeach
        </svg>
        <div class="stats-chart-hits" aria-hidden="true">
            @foreach ($series as $index => $point)
                <button
                    type="button"
                    tabindex="-1"
                    class="stats-chart-hit"
                    x-bind:class="active === {{ $index }} && 'is-active'"
                    x-on:mouseenter="active = {{ $index }}"
                    x-on:click="active = active === {{ $index }} ? null : {{ $index }}"
                ></button>
            @endforeach
        </div>
        @if ($peak !== false && $series[$peak]['count'] > 0)
            <span
                class="stats-chart-value"
                style="left: {{ ($peak + 0.5) / $count * 100 }}%"
                x-show="active === null"
                aria-hidden="true"
            >
                {{ $series[$peak]['count'] }}
            </span>
        @endif
        <span
            class="stats-chart-tip"
            x-cloak
            x-show="active !== null"
            x-text="tips[active]"
            x-bind:style="'left: ' + ((active + 0.5) / {{ $count }} * 100) + '%'"
            x-bind:class="active < {{ $count / 4 }} ? 'is-start' : (active >= {{ $count * 3 / 4 }} ? 'is-end' : '')"
            aria-hidden="true"
        ></span>
    </div>
    <div class="stats-chart-ticks" aria-hidden="true">
        @foreach ($series as $index => $point)
            @if ($hasTick($index))
                @php($isToday = $todayLast && $index === $count - 1)
                <span
                    @class(['stats-chart-tick', 'is-today' => $isToday])
                    style="left: {{ ($index + 0.5) / $count * 100 }}%"
                >
                    {{ $isToday ? __('statistics.chart.today') : $point['label'] }}
                </span>
            @endif
        @endforeach
    </div>

    <table class="sr-only">
        <caption>
            {{ $tableLabel }}
        </caption>
        @foreach ($series as $point)
            <tr>
                <th scope="row">{{ $point['label'] }}</th>
                <td>{{ $point['count'] }}</td>
                @if ($hasPrevious)
                    <td>{{ __('statistics.chart.previous', ['count' => $point['previous'] ?? 0]) }}</td>
                @endif
            </tr>
        @endforeach
    </table>
</div>
