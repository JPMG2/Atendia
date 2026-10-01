@props([
    'title',
    'body',
    'action' => null, // action button label; needs `call` to become a button
    'call' => null,   // Livewire method the action button runs; without it the banner only tells
    'icon' => 'sparkles',
])

<div {{ $attributes->merge(['class' => 'ai-banner']) }}>
    <div class="ai-banner-icon">
        <x-icon :name="$icon" :size="20" />
    </div>
    <div class="ai-banner-copy">
        <p class="ai-banner-title">{{ $title }}</p>
        <p class="ai-banner-body">{{ $body }}</p>
    </div>
    {{-- No label without something to run: a label alone used to print a button
    that did nothing on two screens. Blade reads neither a directive nor an
    interpolated attribute bag inside a component's attribute list, and prints
    them as text instead (it did, in the banner, until 2026-09-30). --}}
    @if ($action !== null && $call !== null)
        <x-ui.button
            variant="secondary"
            size="sm"
            icon="sparkles"
            data-testid="ai-banner-action"
            wire:click="{{ $call }}"
            wire:loading.attr="disabled"
        >
            {{ $action }}</x-ui.button>
    @endif
    {{ $slot }}
</div>
