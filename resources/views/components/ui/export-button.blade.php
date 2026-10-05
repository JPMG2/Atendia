{{-- The ONE Imprimir / Excel / CSV button (owner's call, 2026-09-27): same
colour and shape everywhere, pointing at the single report URL. Guarded by
GoldenRulesReportsTest — no other view may link a report. --}}
@props([
    'report',
    'format' => 'pdf',
    'size' => 'md',
    'params' => [], // what the report needs to answer, e.g. ['mes' => '2026-10']
])

@php
    $format = in_array($format, ['pdf', 'xlsx', 'csv'], true) ? $format : 'pdf';
    $icon = ['pdf' => 'printer', 'xlsx' => 'file-spreadsheet', 'csv' => 'download'][$format];
    $sizeClass = ['sm' => 'btn-sm', 'md' => 'btn-md'][$size] ?? 'btn-md';
@endphp

<a
    href="{{ route('reports.show', array_merge(['report' => $report, 'format' => $format], $params)) }}"
    @if ($format === 'pdf') target="_blank" rel="noopener" @endif
    aria-label="{{ __('reports.aria.'.$format) }}"
    {{ $attributes->merge(['class' => 'btn btn-export '.$sizeClass]) }}
>
    <x-icon :name="$icon" :size="16" />
    <span>{{ __('reports.buttons.'.$format) }}</span>
</a>
