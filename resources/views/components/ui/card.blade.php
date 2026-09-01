@props([
    'interactive' => false,  // hover lift + jade border (golden rule: interactive cards)
    'as' => 'div',           // HTML tag of the container
])

@php
    $classes = 'card'.($interactive ? ' card-interactive' : '');
@endphp

<{{ $as }} {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</{{ $as }}>
