@props([
    'icon' => 'sparkles',
    'title',
    'body',
])

{{-- Empty state that teaches: what will live here and how to fill it. Shared
by the services and products screens so the shape can never diverge. --}}
<x-ui.card class="p-6">
    <div class="mx-auto flex max-w-lg flex-col items-center text-center">
        <div class="flex size-11 items-center justify-center rounded-xl bg-brand-soft">
            <x-icon :name="$icon" :size="22" style="color:var(--brand)" />
        </div>
        <h2 class="font-display mt-3 text-lg font-bold text-strong">{{ $title }}</h2>
        <p class="mt-1 text-sm text-muted">{{ $body }}</p>
    </div>

    <div class="mt-4 flex flex-col items-center gap-3">
        {{ $slot }}
    </div>
</x-ui.card>
