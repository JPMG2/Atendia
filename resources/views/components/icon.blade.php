@props([
    'name',
    'size' => 20,
    'stroke' => 2,
])

@php
    $icon = config("icons.{$name}");

    // Un icono es un string (Lucide, de trazo) o un array ['filled' => true,
    // 'path' => ...] para los logos de marca (Simple Icons, de relleno).
    $filled = is_array($icon) && ($icon['filled'] ?? false);
    $svg = is_array($icon) ? ($icon['path'] ?? null) : $icon;
@endphp

@if ($svg)
    <svg
        xmlns="http://www.w3.org/2000/svg"
        width="{{ $size }}"
        height="{{ $size }}"
        viewBox="0 0 24 24"
        @if ($filled)
            fill="currentColor"
            stroke="none"
        @else
            fill="none"
            stroke="currentColor"
            stroke-width="{{ $stroke }}"
            stroke-linecap="round"
            stroke-linejoin="round"
        @endif
        {{ $attributes->merge(['class' => $filled ? 'brand-icon' : 'lucide', 'aria-hidden' => 'true']) }}
    >{!! $svg !!}</svg>
@endif
