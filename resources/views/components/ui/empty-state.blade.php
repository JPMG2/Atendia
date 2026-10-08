@props([
    'icon',
    'title',
    'body',
    'compact' => false, // nested inside a card section: smaller h3 heading
    'framed' => false,  // its own centred block, for the spot under a toolbar of filters
])

{{-- The same "nothing here yet" block on every screen; wrap it in a card
where it stands alone. `framed` is the one that belongs under a toolbar: the
bare row hugged the filters above it, and a result region that is empty must
look like a place and not like a leftover. --}}
<div {{ $attributes->merge(['class' => 'empty-state '.($framed ? 'empty-framed' : 'flex items-start gap-4')]) }}>
    <div
        class="bg-brand-soft flex size-11 flex-none items-center justify-center rounded-xl"
        style="color: var(--brand)"
    >
        <x-icon :name="$icon" :size="22" />
    </div>
    <div class="min-w-0">
        @if ($compact || $framed)
            <h3 class="text-strong text-sm font-semibold">{{ $title }}</h3>
        @else
            <h2 class="text-strong font-display text-base">{{ $title }}</h2>
        @endif
        <p class="text-body mt-1 text-sm">{{ $body }}</p>
        {{-- Optional: the step that fills this screen, one click away. --}}
        @if (! $slot->isEmpty())
            <div class="mt-4 flex flex-wrap gap-2{{ $framed ? ' justify-center' : '' }}">{{ $slot }}</div>
        @endif
    </div>
</div>
