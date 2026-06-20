@props([
    'variant' => 'info',     // info | success | warning | danger | brand
    'title' => null,
    'icon' => null,
    'dismissible' => false,
])

@php
    $valid = ['info', 'success', 'warning', 'danger', 'brand'];
    $v = in_array($variant, $valid, true) ? $variant : 'info';

    $fg = [
        'info' => 'var(--info)', 'success' => 'var(--success)', 'warning' => 'var(--warning)',
        'danger' => 'var(--danger)', 'brand' => 'var(--brand)',
    ][$v];
@endphp

<div
    role="status"
    @if ($dismissible) x-data="{ show: true }" x-show="show" @endif
    {{ $attributes->merge(['class' => 'alert alert-'.$v]) }}
>
    @if ($icon)
        <span class="alert-icon" style="color:{{ $fg }};"><x-icon :name="$icon" :size="20" /></span>
    @endif

    <div class="flex-1 min-w-0">
        @if ($title)
            <div class="alert-title" @if (! $slot->isEmpty()) style="margin-bottom:3px;" @endif>{{ $title }}</div>
        @endif
        @if (! $slot->isEmpty())
            <div class="alert-body">{{ $slot }}</div>
        @endif
    </div>

    @if ($dismissible)
        <button type="button" class="alert-close" aria-label="Cerrar" @click="show = false">&times;</button>
    @endif
</div>
