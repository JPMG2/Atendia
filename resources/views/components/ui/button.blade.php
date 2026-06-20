@props([
    'variant' => 'primary',   // primary | secondary | ghost | accent
    'size' => 'md',           // sm | md | lg
    'href' => null,           // si se pasa, renderiza <a>; si no, <button>
    'type' => 'button',
    'fullWidth' => false,
    'icon' => null,           // nombre de icono a la izquierda
    'iconRight' => null,      // nombre de icono a la derecha
])

@php
    // Mapas blindados: una variante/tamaño inválido cae en el default,
    // nunca genera una clase rota. Todo el color sale de tokens → dark/light solos.
    $variants = ['primary' => 'btn-primary', 'secondary' => 'btn-secondary', 'ghost' => 'btn-ghost', 'accent' => 'btn-accent'];
    $sizes = ['sm' => 'btn-sm', 'md' => 'btn-md', 'lg' => 'btn-lg'];

    $classes = 'btn '
        .($variants[$variant] ?? $variants['primary']).' '
        .($sizes[$size] ?? $sizes['md'])
        .($fullWidth ? ' w-full' : '');

    $iconSize = ['sm' => 16, 'md' => 18, 'lg' => 19][$size] ?? 18;

    $tag = $href ? 'a' : 'button';
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" @else type="{{ $type }}" @endif
    {{ $attributes->merge(['class' => $classes]) }}
>
    @if ($icon)<x-icon :name="$icon" :size="$iconSize" />@endif
    {{ $slot }}
    @if ($iconRight)<x-icon :name="$iconRight" :size="$iconSize" />@endif
</{{ $tag }}>
