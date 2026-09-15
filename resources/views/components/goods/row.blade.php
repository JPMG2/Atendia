@props([
    'service',
    'index',
    'needle' => '',
])

{{-- One service row, shared by the grouped shelves and the flat search
results: state rides next to the name, actions sit on the row. --}}
<li {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-x-4 gap-y-1 rounded-lg px-2 py-2.5 transition hover:bg-sunken']) }}>
    <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-2">
            @if ($service['featured'])
                <x-icon name="star" :size="14" style="color: var(--brand)" />
            @endif
            <p class="text-strong text-sm font-semibold"><x-ui.match :text="$service['name']" :needle="$needle" /></p>
            @if ($service['active'])
                <x-ui.badge variant="brand" :dot="true">{{ __('client.services.active') }}</x-ui.badge>
            @else
                <x-ui.badge variant="neutral">{{ __('client.services.paused') }}</x-ui.badge>
            @endif
        </div>
        @if ($service['description'] !== null)
            <p class="text-muted text-sm">{{ $service['description'] }}</p>
        @endif
    </div>
    <div class="text-right">
        @if ($service['price'] !== null)
            <p class="text-strong font-mono text-base font-bold">
                $ {{ number_format($service['price'], 0, ',', '.') }}
            </p>
        @else
            <span class="rounded-full bg-[color:var(--warning-soft)] px-2 py-0.5 text-xs font-semibold text-[color:var(--warning)]">
                {{ __('client.services.no_price') }}
            </span>
        @endif
        <p class="text-subtle flex items-center justify-end gap-2 font-mono text-xs">
            @if ($service['minutes'] !== null)
                <span class="flex items-center gap-1"><x-icon name="clock" :size="12" />{{ $service['minutes'] }} min</span>
            @endif
            @if ($service['deposit'] !== null)
                <span>{{ __('client.services.deposit_short', ['amount' => number_format($service['deposit'], 0, ',', '.')]) }}</span>
            @endif
        </p>
    </div>
    <div class="flex items-center gap-1">
        <x-ui.icon-button
            icon="pencil"
            size="sm"
            variant="ghost"
            wire:click="edit({{ $index }})"
            :label="__('client.services.edit', ['name' => $service['name']])"
        />
        <x-ui.icon-button
            :icon="$service['active'] ? 'pause' : 'play'"
            size="sm"
            variant="ghost"
            :label="__($service['active'] ? 'client.services.pause' : 'client.services.resume', ['name' => $service['name']])"
        />
    </div>
</li>
