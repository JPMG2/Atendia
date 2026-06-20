@props([
    'label',
    'value',
    'delta' => null,        // ej. "+12%"
    'trend' => 'up',        // up | down | flat
    'icon' => null,
    'tint' => 'brand',      // brand | accent | info | warning
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

<div {{ $attributes->merge(['class' => 'stat-card']) }}>
    <div class="flex items-center justify-between">
        <span class="stat-label">{{ $label }}</span>
        @if ($icon)
            <span class="stat-icon" style="background:{{ $bg }};color:{{ $fg }};"><x-icon :name="$icon" :size="20" /></span>
        @endif
    </div>
    <div class="flex items-baseline gap-2.5 flex-wrap">
        <span class="stat-value">{{ $value }}</span>
        @if ($delta)
            <span class="inline-flex items-center gap-1" style="font-size:var(--text-sm);font-weight:600;color:{{ $trendColor }};">{{ $arrow }} {{ $delta }}</span>
        @endif
    </div>
</div>
