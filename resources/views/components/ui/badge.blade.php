@props([
    'variant' => 'brand',  // brand | accent
    'dot' => false,        // muestra un punto del color de la variante
])

@php
    $variants = ['brand' => 'badge-brand', 'accent' => 'badge-accent'];
    $classes = 'badge '.($variants[$variant] ?? $variants['brand']);
    $dotColor = $variant === 'accent' ? 'var(--accent)' : 'var(--brand)';
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    @if ($dot)
        <span class="inline-block" style="width:.375rem;height:.375rem;border-radius:var(--radius-pill);background:{{ $dotColor }};"></span>
    @endif
    {{ $slot }}
</span>
