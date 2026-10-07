<?php

use App\Enums\WhatsAppLinkState;
use App\Models\Business;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The topbar's WhatsApp status. It is a component of its own because the layout
 * renders once per page load: the pill read "sin conectar" through the very scan
 * that connected the number, which is the one thing a status must never do.
 */
new class extends Component
{
    /** True from the pairing until the greeting has had its five seconds. */
    public bool $greeting = false;

    /**
     * Two ways the truth reaches the pill: the card next to it when she links
     * the number, and the panel socket when it falls while she is elsewhere.
     * The bell already rides that socket, so a fall costs no poll of our own.
     *
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        $businessId = Auth::user()?->business_id;

        return [
            'whatsapp:connected' => 'linked',
            ...($businessId === null ? [] : ["echo-private:business.{$businessId},.panel.notified" => '$refresh']),
        ];
    }

    #[Computed]
    public function business(): ?Business
    {
        return Auth::user()?->business;
    }

    /** Read on every roundtrip, so the pill cannot outlive the claim it makes. */
    #[Computed]
    public function state(): WhatsAppLinkState
    {
        return $this->business?->linkState() ?? WhatsAppLinkState::Disconnected;
    }

    /** Green with no date is a claim; green with one is a measurement. */
    #[Computed]
    public function verifiedAgo(): ?string
    {
        return $this->business?->linkVerifiedAt()?->diffForHumans();
    }

    /**
     * The number that just answered. Only read for the greeting: the rest of
     * the time the pill is a colour and a word, and owes the bridge nothing.
     *
     * @return array{number: string, name: string, picture: ?string}|null
     */
    #[Computed]
    public function profile(): ?array
    {
        return $this->greeting ? $this->business?->whatsAppProfile() : null;
    }

    public function linked(): void
    {
        $this->greeting = true;

        unset($this->business, $this->state, $this->verifiedAgo, $this->profile);
    }

    /** Alpine hands the greeting back after five seconds; the pill shortens again. */
    public function greeted(): void
    {
        $this->greeting = false;

        unset($this->profile);
    }
};
?>

{{-- The root takes no box of its own: topbar-actions is a flex row, and a plain
wrapper would make the pill a block child instead of one of its items. --}}
<div class="conn-pill-slot">
    @if ($this->state === WhatsAppLinkState::Connected)
        @if ($this->profile !== null && $this->profile['name'] !== '')
            {{-- The one moment the pill is worth widening: she can still undo a
            wrong number, and this is where she finds out which one answered. --}}
            <span
                class="conn-pill conn-pill-greeting"
                data-testid="conn-pill-greeting"
                x-data
                x-init="setTimeout(() => $wire.greeted(), 5000)"
            >
                <x-ui.avatar :src="$this->profile['picture']" :name="$this->profile['name']" size="xs" />
                {{ __('whatsapp.topbar_connected_as', ['name' => $this->profile['name']]) }}
            </span>
        @else
            <span
                class="conn-pill"
                data-testid="conn-pill-connected"
                @if ($this->verifiedAgo !== null) title="{{ __('whatsapp.topbar_verified_ago', ['ago' => $this->verifiedAgo]) }}" @endif
            >
                <span class="conn-dot"></span>{{ __('whatsapp.topbar_connected') }}
            </span>
        @endif
    @else
        <a
            href="{{ route('whatsapp') }}"
            wire:navigate
            class="conn-pill conn-pill-warning"
            data-testid="conn-pill-{{ $this->state->value }}"
        >
            <span class="conn-dot"></span>
            {{
                $this->state === WhatsAppLinkState::Unverified
                ? __('whatsapp.topbar_unverified')
                : __('whatsapp.topbar_disconnected')
            }}
        </a>
    @endif
</div>