@props([
    'items', // list of {key, count, icon, url}: AdminAttention::$links
])

{{-- "Lo que necesita tu atención hoy": what waits for the admin, each line a door to its screen. The assistant's
panel and the admin's home print this same card, so the two say the same thing. --}}
<div {{ $attributes->merge(['class' => 'ask-today']) }}>
    <p class="ask-today-title">
        <x-icon name="bell" :size="16" />
        {{ __('ask_admin.today.title') }}
    </p>
    @forelse ($items as $item)
        <a href="{{ $item['url'] }}" wire:navigate wire:key="admin-today-{{ $item['key'] }}" class="ask-today-row">
            <x-icon :name="$item['icon']" :size="16" />
            <span>{{ trans_choice('ask_admin.today.'.$item['key'], $item['count'], ['count' => $item['count']]) }}</span>
            <x-icon name="chevron-right" :size="16" class="ask-today-go" />
        </a>
    @empty
        <p class="ask-today-clear">{{ __('ask_admin.today.clear') }}</p>
    @endforelse
</div>
