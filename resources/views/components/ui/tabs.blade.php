@props([
    'tabs' => [],        // [['value'=>'a','label'=>'A','icon'=>?,'badge'=>?], ...]
    'default' => null,
])

@php
    $active = $default ?? ($tabs[0]['value'] ?? null);
@endphp

{{-- Estado en Alpine: la barra alterna `tab`; los paneles del slot usan x-show="tab === '...'". --}}
<div x-data="{ tab: @js($active) }" {{ $attributes }}>
    <div role="tablist" class="tabs">
        @foreach ($tabs as $t)
            <button
                type="button"
                role="tab"
                class="tab"
                :class="{ 'tab-active': tab === @js($t['value']) }"
                :aria-selected="tab === @js($t['value'])"
                @click="tab = @js($t['value'])"
            >
                @isset($t['icon'])<x-icon :name="$t['icon']" :size="16" />@endisset
                <span>{{ $t['label'] }}</span>
                @isset($t['badge'])<span class="tab-badge">{{ $t['badge'] }}</span>@endisset
            </button>
        @endforeach
    </div>

    {{ $slot }}
</div>
