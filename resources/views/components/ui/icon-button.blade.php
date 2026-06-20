@props([
    'icon' => null,           // nombre del icono (o usar el slot para casos especiales)
    'size' => 'md',           // sm | md | lg
    'variant' => 'secondary', // secondary | ghost
    'label' => null,          // aria-label — obligatorio para accesibilidad si no hay texto
])

@php
    $sizes = ['sm' => 'icon-btn-sm', 'md' => 'icon-btn-md', 'lg' => 'icon-btn-lg'];
    $variants = ['secondary' => 'icon-btn-secondary', 'ghost' => 'icon-btn-ghost'];

    $classes = 'icon-btn '
        .($variants[$variant] ?? $variants['secondary']).' '
        .($sizes[$size] ?? $sizes['md']);

    $iconSize = ['sm' => 16, 'md' => 18, 'lg' => 20][$size] ?? 18;
@endphp

<button
    type="button"
    @if ($label) aria-label="{{ $label }}" @endif
    {{ $attributes->merge(['class' => $classes]) }}
>
    @if ($slot->isEmpty())
        <x-icon :name="$icon" :size="$iconSize" />
    @else
        {{ $slot }}
    @endif
</button>
