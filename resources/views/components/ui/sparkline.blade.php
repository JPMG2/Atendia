@props([
    'values' => [],     // one number per period, oldest first
    'label' => null,    // what the shape is of, read by a screen reader
    'width' => 104,
    'height' => 20,
])

@php
    $values = array_values(array_map('floatval', $values));
    $count = count($values);
    $top = $count > 0 ? max($values) : 0.0;

    // A flat run has no shape to scale: drawn against its own maximum it would
    // swing like a mountain range over rounding noise, so it stays a flat line.
    $points = $count < 2 ? '' : collect($values)
        ->map(function (float $value, int $index) use ($count, $top, $width, $height): string {
            $x = $index * ($width / ($count - 1));
            $y = $top > 0 ? $height - ($value / $top) * ($height - 2) - 1 : $height / 2;

            return round($x, 1).','.round($y, 1);
        })
        ->implode(' ');

    $lastX = $width;
    $lastY = $count > 1 && $top > 0
        ? round($height - ($values[$count - 1] / $top) * ($height - 2) - 1, 1)
        : $height / 2;
@endphp

@if ($points !== '')
    <span {{ $attributes->merge(['class' => 'sparkline']) }} role="img" @if ($label) aria-label="{{ $label }}" @endif>
        <svg
            width="{{ $width }}"
            height="{{ $height }}"
            viewBox="0 0 {{ $width }} {{ $height }}"
            fill="none"
            aria-hidden="true"
        >
            <polyline
                points="{{ $points }}"
                stroke="currentColor"
                stroke-width="1.5"
                stroke-linecap="round"
                stroke-linejoin="round"
            />
            {{-- The last point is the one she is looking at: without it the
            line just stops and the current month reads like any other. --}}
            <circle cx="{{ $lastX }}" cy="{{ $lastY }}" r="2" fill="currentColor" />
        </svg>
    </span>
@endif
