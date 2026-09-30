@props([
    'title',
    'body',
    'action' => null, // action button label; omit it for a purely informative banner
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
    {{-- Two branches, no cleverness: Blade reads neither a directive nor an
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
    @elseif ($action !== null)
        <x-ui.button variant="secondary" size="sm" icon="sparkles">{{ $action }}</x-ui.button>
    @endif
    {{ $slot }}
</div>
