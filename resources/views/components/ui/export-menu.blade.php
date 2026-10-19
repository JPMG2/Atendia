{{-- The one Exportar control (owner's call, 2026-10-19): a single neutral button that opens the
three formats, and — only when a filter is active — asks whether to export what is on screen or
everything. The report class holds the lock and the cut; this only carries the screen's state. --}}
@props([
    'report',
    'params' => [], // the screen's own filters: the report reads them through ReportScope
])

@php
    $source = app(config('atendia.reports.'.$report));
    $summary = $source instanceof \App\Classes\Report\ListReport ? $source->summary($params) : null;
    $filters = $summary['filters'] ?? [];
    // A client's documents carry the business's tax id; when it is missing, say where to add it.
    $missingTaxId = $source instanceof \App\Classes\Report\ListReport
        && $source->audience === \App\Enums\ReportAudience::Client
        && auth()->user()?->business !== null
        && blank(auth()->user()->business->tax_id)
        && auth()->user()->can('manage-business');
    $filterText = collect($filters)->map(fn (array $filter): string => $filter['label'].': '.$filter['value'])->implode(' · ');
@endphp

<div
    {{ $attributes->merge(['class' => 'export-menu']) }}
    x-data="{ open: false, scope: 'view' }"
    x-on:keydown.escape.window="open = false"
>
    <button
        type="button"
        class="btn btn-export"
        x-on:click="open = ! open"
        x-bind:aria-expanded="open"
        aria-haspopup="true"
    >
        <x-icon name="download" :size="16" />
        <span>{{ __('reports.menu.button') }}</span>
        <x-icon name="chevron-down" :size="16" class="export-chev" />
    </button>

    <div
        class="export-panel"
        role="menu"
        x-show="open"
        x-cloak
        x-transition.opacity.duration.120ms
        x-on:click.outside="open = false"
    >
        @if ($filters !== [])
            <p class="export-sub">{{ __('reports.menu.scope_title') }}</p>
            <div class="export-scope" role="radiogroup">
                <button
                    type="button"
                    class="export-option"
                    role="radio"
                    x-bind:aria-checked="scope === 'view'"
                    x-on:click="scope = 'view'"
                >
                    <b
                        >{{ __('reports.menu.view') }}
                        <span class="export-tag">{{ __('reports.menu.recommended') }}</span></b>
                    <span>{{ __('reports.menu.view_hint', ['count' => trans_choice('reports.records', $summary['shown'], ['count' => $summary['shown']]), 'filters' => $filterText]) }}</span>
                </button>
                <button
                    type="button"
                    class="export-option"
                    role="radio"
                    x-bind:aria-checked="scope === 'all'"
                    x-on:click="scope = 'all'"
                >
                    <b>{{ __('reports.menu.all') }}</b>
                    <span>{{ __('reports.menu.all_hint', ['count' => trans_choice('reports.records', $summary['total'], ['count' => $summary['total']])]) }}</span>
                </button>
            </div>
        @endif

        <p class="export-sub">{{ __('reports.menu.format') }}</p>
        @foreach (['pdf', 'xlsx', 'csv'] as $format)
            <x-ui.export-button
                :report="$report"
                :format="$format"
                :params="$params"
                wire:key="export-{{ $report }}-{{ $format }}"
            />
        @endforeach

        @if ($filters !== [])
            <p class="export-note">{{ __('reports.menu.note') }}</p>
        @endif

        @if ($missingTaxId)
            <p class="export-note">
                {{ __('reports.menu.tax_id_missing') }}
                <a
                    class="row-link"
                    wire:navigate
                    href="{{ route('my-business.facturacion') }}"
                >{{ __('reports.menu.tax_id_add') }}</a>
            </p>
        @endif
    </div>
</div>
