@props([
    'name',
    'size' => 20,
    'stroke' => 2,
])

@php
    $svg = config("icons.{$name}");
@endphp

@if ($svg)
    <svg
        xmlns="http://www.w3.org/2000/svg"
        width="{{ $size }}"
        height="{{ $size }}"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="{{ $stroke }}"
        stroke-linecap="round"
        stroke-linejoin="round"
        {{ $attributes->merge(['class' => 'lucide', 'aria-hidden' => 'true']) }}
    >{!! $svg !!}</svg>
@endif
