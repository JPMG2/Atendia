@props([
    'text' => '',
    'needle' => '',
])

@php
    // Server twin of the catalog rail highlight. Folds accents on both sides
    // (assumes the fold keeps length — true for Spanish content); under 3
    // characters a mark would speckle nearly every row, so the text stays clean.
    $needle = trim($needle);
    $position = mb_strlen($needle) >= 3
        ? mb_strpos(Str::ascii(mb_strtolower($text)), Str::ascii(mb_strtolower($needle)))
        : false;
@endphp

@if ($position === false)
    {{ $text }}
@else
    {{ mb_substr($text, 0, $position) }}<span
     class="match-hit">{{ mb_substr($text, $position, mb_strlen($needle)) }}</span
    >{{ mb_substr($text, $position + mb_strlen($needle)) }}
@endif
