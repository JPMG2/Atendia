@props([
    'variant' => 'brand',  // brand | accent | neutral
    'dot' => false,        // shows a dot in the variant's colour
    'pulse' => false,      // the dot breathes softly (live/online cues)
])

@php
    $variants = ['brand' => 'badge-brand', 'accent' => 'badge-accent', 'neutral' => 'badge-neutral'];
    $dots = ['brand' => 'var(--brand)', 'accent' => 'var(--accent)', 'neutral' => 'var(--text-subtle)'];
    $classes = 'badge '.($variants[$variant] ?? $variants['brand']);
    $dotColor = $dots[$variant] ?? $dots['brand'];
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    @if ($dot)
        <span
            class="inline-block{{ $pulse ? ' badge-dot-pulse' : '' }}"
            style="width:.375rem;height:.375rem;border-radius:var(--radius-pill);background:{{ $dotColor }};"
        ></span>
    @endif
    {{ $slot }}
</span>
