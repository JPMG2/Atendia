@props([
    'icon',
    'title',
    'body',
    'compact' => false, // nested inside a card section: smaller h3 heading
])

{{-- The same "nothing here yet" block on every screen; wrap it in a card
where it stands alone. --}}
<div {{ $attributes->merge(['class' => 'flex items-start gap-4']) }}>
    <div
        class="bg-brand-soft flex size-11 flex-none items-center justify-center rounded-xl"
        style="color: var(--brand)"
    >
        <x-icon :name="$icon" :size="22" />
    </div>
    <div class="min-w-0">
        @if ($compact)
            <h3 class="text-strong text-sm font-semibold">{{ $title }}</h3>
        @else
            <h2 class="text-strong font-display text-base">{{ $title }}</h2>
        @endif
        <p class="text-body mt-1 text-sm">{{ $body }}</p>
    </div>
</div>
