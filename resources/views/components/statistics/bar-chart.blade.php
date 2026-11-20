{{-- One bar chart for every statistics block: single jade series (magnitude
needs one hue), thin marks with a 2px gap, the peak bar at full strength with
its value as the only direct label, native tooltips, and a screen-reader
table so the data never lives in color alone. --}}
@props([
    'series' => [],
    'labelEvery' => 7,
    'tableLabel' => '',
])

@php
    $max = max(1, collect($series)->max('count'));
    $peak = collect($series)->search(fn (array $point): bool => $point['count'] === collect($series)->max('count'));
    // Each bar gets a slot of at least 1/300 of the width: with few bars
    // (six months) a narrow viewBox scaled to full width grew huge.
    $slot = max(10, 300 / max(1, count($series)));
    $barWidth = min($slot - 3, 24);
    $chartHeight = 96;
    $width = count($series) * $slot;
@endphp

{{-- Labels are HTML laid over the SVG, not SVG text: the SVG scales with its
card, so text inside it went from 9px on a phone to 16px at 900px. --}}
<div class="stats-chart">
    <div class="stats-chart-plot">
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
                >
                    <title>{{ $point['label'] }}: {{ $point['count'] }}</title>
                </rect>
            @endforeach
        </svg>
        @if ($peak !== false && $series[$peak]['count'] > 0)
            <span
                class="stats-chart-value"
                style="left: {{ ($peak + 0.5) / count($series) * 100 }}%"
                aria-hidden="true"
            >
                {{ $series[$peak]['count'] }}
            </span>
        @endif
    </div>
    <div class="stats-chart-ticks" aria-hidden="true">
        @foreach ($series as $index => $point)
            @if ($index % $labelEvery === 0)
                <span class="stats-chart-tick" style="left: {{ ($index + 0.5) / count($series) * 100 }}%">
                    {{ $point['label'] }}
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
            </tr>
        @endforeach
    </table>
</div>
