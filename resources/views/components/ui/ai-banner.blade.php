@props([
    'title',
    'body',
    'action' => null, // action button label; omit it for a purely informative banner
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
    @if ($action !== null)
        <x-ui.button variant="secondary" size="sm" icon="sparkles">{{ $action }}</x-ui.button>
    @endif
    {{ $slot }}
</div>
