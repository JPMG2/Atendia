<?php

use App\Models\LoginActivity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/** Read-only trail of the account's latest sign-ins. */
new class extends Component
{
    /** @return Collection<int, LoginActivity> */
    #[Computed]
    public function activities(): Collection
    {
        return Auth::user()->recentLoginActivity();
    }
};
?>

<x-ui.card id="actividad" class="bp-card">
    <div class="bp-card-head">
        <h2>{{ __('profile.activity.title') }}</h2>
    </div>
    <p class="bp-card-sub">{{ __('profile.activity.sub') }}</p>

    <ul>
        @forelse ($this->activities as $activity)
            <li
                wire:key="activity-{{ $activity->id }}"
                class="bd-subtle flex flex-wrap items-center gap-3 border-b py-2 last:border-b-0"
            >
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
