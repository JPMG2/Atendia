@props([
    'interactive' => false,  // hover lift + borde jade (regla de oro: cards interactivas)
    'as' => 'div',           // etiqueta HTML del contenedor
])

@php
    $classes = 'card'.($interactive ? ' card-interactive' : '');
@endphp

<{{ $as }} {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</{{ $as }}>
