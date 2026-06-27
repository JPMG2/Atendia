@props(['items'])

{{-- Recursive navigation menu. Renders a list of <x-ui.menu-item>, each of
     which recurses into its own children to arbitrary depth. --}}
<ul {{ $attributes->merge(['class' => 'menu']) }} role="list">
    @foreach ($items as $item)
        <x-ui.menu-item :item="$item" wire:key="menu-{{ $item->id }}" />
    @endforeach
</ul>
