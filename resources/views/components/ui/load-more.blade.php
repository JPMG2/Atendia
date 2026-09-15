@props([
    'shown' => 0,
    'total' => 0,
    'action' => 'loadMore',
])

{{-- Hybrid list footer (NN/g): a visible button AND wire:intersect on it, so
scrolling to the bottom keeps loading hands-free while the button keeps the
user in control. Rendered only while there is more to load. --}}
@if ($shown < $total)
    <div {{ $attributes->merge(['class' => 'mt-4 flex flex-col items-center gap-2']) }}>
        <p class="text-subtle font-mono text-xs">
            {{ __('pagination.showing', ['shown' => $shown, 'total' => $total]) }}
        </p>
        <x-ui.button
            variant="secondary"
            size="sm"
            class="data-loading:opacity-50"
            wire:click.preserve-scroll="{{ $action }}"
            wire:intersect.margin.100px.preserve-scroll="{{ $action }}"
        >
            {{ __('pagination.load_more') }}
        </x-ui.button>
    </div>
@endif
