@props(['user'])

{{-- Read-only trail, so no Livewire: the data comes straight from the
domain method and the page renders it once. --}}
<x-ui.card class="p-5 sm:p-6">
    <h2 class="text-strong font-display" style="font-size: var(--text-lg); font-weight: 700">
        {{ __('profile.activity.title') }}
    </h2>
    <p class="text-muted mt-1" style="font-size: var(--text-sm)">{{ __('profile.activity.sub') }}</p>

    <ul class="mt-4">
        @forelse ($user->recentLoginActivity() as $activity)
            <li class="bd-subtle flex flex-wrap items-center gap-3 border-b py-2 last:border-b-0">
                <x-icon
                    :name="$activity->isMobile() ? 'smartphone' : 'monitor'"
                    :size="16"
                    style="color: var(--text-subtle)"
                />
                <span class="text-strong font-semibold" style="font-size: var(--text-sm)">
                    {{ $activity->label() }}
                </span>
                <span class="text-muted" style="font-size: var(--text-xs)">
                    <span class="font-mono">{{ $activity->ip }}</span>
                    @if ($activity->location)
                        · {{ $activity->location }}
                    @endif
                    · {{ $activity->created_at->diffForHumans() }}
                </span>
            </li>
        @empty
            <li class="text-muted" style="font-size: var(--text-sm)">{{ __('profile.activity.empty') }}</li>
        @endforelse
    </ul>
</x-ui.card>
