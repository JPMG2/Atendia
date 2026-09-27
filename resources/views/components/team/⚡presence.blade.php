<?php

use App\Classes\Main\Client;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * "Tu estado" in the avatar menu: one click from anywhere decides whether
 * this person gets the handoff pings right now.
 */
new class extends Component
{
    public bool $available = true;

    public function mount(): void
    {
        $this->available = (bool) Auth::user()?->is_available;
    }

    public function toggle(): void
    {
        $this->available = (bool) Client::for(Auth::user())->account->setAvailability(! $this->available)->is_available;
        $this->dispatch('presence-changed', available: $this->available);
    }
};
?>

<div class="dropdown-presence">
    <p class="eyebrow">{{ __('team.availability.label') }}</p>
    <button type="button" @class(['presence-toggle', 'is-away' => ! $available]) wire:click="toggle" aria-pressed="{{ $available ? 'true' : 'false' }}">
        <span class="presence-dot"></span>
        <span>{{ $available ? __('team.availability.available') : __('team.availability.away') }}</span>
        <span class="presence-switch" aria-hidden="true"></span>
    </button>
    <p class="presence-hint">{{ $available ? __('team.availability.hint_available') : __('team.availability.hint_away') }}</p>
</div>
