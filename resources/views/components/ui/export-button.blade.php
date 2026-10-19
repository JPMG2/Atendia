{{-- One format in the export menu, and the ONLY place that links a report (guarded by
GoldenRulesReportsTest). It reads `scope` and `open` from the menu around it: "all" sends the
same URL with alcance=todo, so the report ignores every filter. --}}
@props([
    'report',
    'format' => 'pdf',
    'params' => [], // what the report needs to answer, e.g. ['buscar' => 'ana', 'mes' => '2026-10']
])

@php
    $format = in_array($format, ['pdf', 'xlsx', 'csv'], true) ? $format : 'pdf';
    $icon = ['pdf' => 'printer', 'xlsx' => 'file-spreadsheet', 'csv' => 'file-text'][$format];
    $query = array_filter($params, fn (mixed $value): bool => $value !== '' && $value !== null);
    $viewUrl = route('reports.show', array_merge(['report' => $report, 'format' => $format], $query));
    $allUrl = route('reports.show', array_merge(['report' => $report, 'format' => $format], $query, ['alcance' => 'todo']));
@endphp

<a
    href="{{ $viewUrl }}"
    x-bind:href="scope === 'all' ? {{ \Illuminate\Support\Js::from($allUrl) }} : {{ \Illuminate\Support\Js::from($viewUrl) }}"
    @if ($format === 'pdf') target="_blank" rel="noopener" @endif
    x-on:click="open = false"
    role="menuitem"
    aria-label="{{ __('reports.aria.'.$format) }}"
    {{ $attributes->merge(['class' => 'export-format']) }}
>
    <span class="export-tile"><x-icon :name="$icon" :size="17" /></span>
    <div>
        <b>{{ __('reports.menu.formats.'.$format.'.title') }}</b>
        <span>{{ __('reports.menu.formats.'.$format.'.hint') }}</span>
    </div>
</a>
