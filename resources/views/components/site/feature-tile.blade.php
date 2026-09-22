@props(['icon', 'title', 'body'])

<x-ui.card interactive {{ $attributes }} style="padding: 24px">
    <span
        class="mb-4 inline-flex items-center justify-center"
        style="
            width: 46px;
            height: 46px;
            border-radius: var(--radius-md);
            background: var(--brand-soft);
            color: var(--brand);
        "
    >
        <x-icon :name="$icon" :size="23" />
    </span>
    <h3 class="mb-2 font-display" style="font-size: var(--text-xl)">{{ $title }}</h3>
    <p @class(['text-muted', 'mb-4' => ! $slot->isEmpty()]) style="font-size: var(--text-sm); line-height: 1.55">
        {{ $body }}
    </p>
    {{ $slot }}
</x-ui.card>
