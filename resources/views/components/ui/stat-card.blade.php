@props([
    'label',
    'value',
    'delta' => null,        // a short figure like "+12%", never a sentence
    'trend' => 'up',        // up | down | flat
    'icon' => null,
    'tint' => 'brand',      // brand | accent | info | warning
    'href' => null,         // with one, the tile IS the link to its queue
])

@php
    $tints = [
        'brand' => ['var(--brand-soft)', 'var(--brand-soft-text)'],
        'accent' => ['var(--accent-soft)', 'var(--accent-soft-text)'],
        'info' => ['var(--info-soft)', 'var(--info)'],
        'warning' => ['var(--warning-soft)', 'var(--warning)'],
    ];
    [$bg, $fg] = $tints[$tint] ?? $tints['brand'];

    $trendColor = ['up' => 'var(--success)', 'down' => 'var(--danger)', 'flat' => 'var(--text-muted)'][$trend] ?? 'var(--success)';
    $arrow = ['up' => '↑', 'down' => '↓', 'flat' => '→'][$trend] ?? '↑';
@endphp

{{-- A tile that counts a queue takes her to it: counting without the door
makes her look for what the screen already found (atendiadesign §7.2). --}}
@php($tag = $href ? 'a' : 'div')

<{{ $tag }}
    @if ($href) href="{{ $href }}" wire:navigate @endif
    {{ $attributes->merge(['class' => 'stat-card'.($href ? ' is-link' : '')]) }}
>
    <div class="flex items-center justify-between gap-3">
        <span class="stat-label">{{ $label }}</span>
        @if ($icon)
            <span class="stat-icon" style="background:{{ $bg }};color:{{ $fg }};"
                ><x-icon :name="$icon" :size="16"
            /></span>
        @endif
    </div>
    <div class="flex flex-wrap items-baseline gap-2.5">
        <span class="stat-value">{{ $value }}</span>
        @if ($delta)
            <span
                class="inline-flex items-center gap-1"
                style="font-size:var(--text-sm);font-weight:600;color:{{ $trendColor }};"
            >{{ $arrow }} {{ $delta }}</span>
        @endif
    </div>

    {{-- A figure without its context is not understood: the slot is where the
    caller says what it is made of ("4 suscripciones pagando"). --}}
    @if (filled(trim($slot)))
        <p class="stat-foot">{{ $slot }}</p>
    @endif
</{{ $tag }}>
