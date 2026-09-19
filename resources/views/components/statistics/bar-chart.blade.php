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
    $barWidth = 7;
    $gap = 3;
    $chartHeight = 96;
    $width = count($series) * ($barWidth + $gap);
@endphp

<div class="stats-chart">
    <svg
        viewBox="0 0 {{ $width }} {{ $chartHeight + 18 }}"
        role="img"
        aria-label="{{ $tableLabel }}"
        preserveAspectRatio="none"
    >
        @foreach ($series as $index => $point)
            @php
                $height = $point['count'] > 0 ? max(3, (int) round($point['count'] / $max * $chartHeight)) : 2;
                $x = $index * ($barWidth + $gap);
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
            @if ($isPeak)
                <text
                    x="{{ $x + $barWidth / 2 }}"
                    y="{{ max(9, $chartHeight - $height - 5) }}"
                    text-anchor="middle"
                    class="stats-chart-value"
                >
                    {{ $point['count'] }}
                </text>
            @endif
            @if ($index % $labelEvery === 0)
                <text
                    x="{{ $x + $barWidth / 2 }}"
                    y="{{ $chartHeight + 13 }}"
                    text-anchor="middle"
                    class="stats-chart-tick"
                >
                    {{ $point['label'] }}
                </text>
            @endif
        @endforeach
    </svg>

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
