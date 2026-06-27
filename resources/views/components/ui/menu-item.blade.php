@props(['item', 'depth' => 0])

{{-- A single menu node. Parents with children become an Alpine-collapsible
     group (auto-open when a descendant is the current route); leaves are
     wire:navigate links that Livewire flags with data-current when active. --}}
@if ($item->hasChildren())
    <li {{ $attributes->merge(['class' => 'menu-node']) }} x-data="{ open: @js($item->hasActiveDescendant()) }">
        <button
            type="button"
            class="menu-item menu-item-toggle"
            style="--menu-depth: {{ $depth }}"
            @click="open = ! open"
            :aria-expanded="open"
        >
            @if ($item->icon)
                <x-icon :name="$item->icon" :size="19" class="menu-item-icon" />
            @endif
            <span class="menu-item-label">{{ $item->label }}</span>
            @if (filled($item->badge))
                <span class="menu-badge">{{ $item->badge }}</span>
            @endif
            <x-icon name="chevron-down" :size="16" class="menu-caret" x-bind:class="{ 'menu-caret-open': open }" />
        </button>

        <ul class="menu-children" role="list" x-show="open" x-cloak>
            @foreach ($item->childrenRecursive as $child)
                <x-ui.menu-item :item="$child" :depth="$depth + 1" wire:key="menu-{{ $child->id }}" />
            @endforeach
        </ul>
    </li>
@else
    <li {{ $attributes->merge(['class' => 'menu-node']) }}>
        <a
            href="{{ $item->url ?? '#' }}"
            @if ($item->url) wire:navigate @endif
            class="menu-item"
            style="--menu-depth: {{ $depth }}"
        >
            @if ($item->icon)
                <x-icon :name="$item->icon" :size="19" class="menu-item-icon" />
            @endif
            <span class="menu-item-label">{{ $item->label }}</span>
            @if (filled($item->badge))
                <span class="menu-badge">{{ $item->badge }}</span>
            @endif
        </a>
    </li>
@endif
