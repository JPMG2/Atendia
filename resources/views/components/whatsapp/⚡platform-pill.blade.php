<?php

use App\Enums\PlatformWhatsAppState;
use App\Models\Company;
use App\Services\PlatformWhatsApp;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The admin topbar's twin of the client's WhatsApp pill, for the platform's OWN
 * line. It never asks the bridge (it runs on every admin page): it reads what
 * the screen or the board last learned, and it polls so a fall shows by itself.
 * The lock is the route's permission; this only declines to draw the pill.
 */
new class extends Component
{
    /** @return array{state: PlatformWhatsAppState, at: ?\Carbon\CarbonImmutable, since: ?\Carbon\CarbonImmutable} */
    #[Computed]
    public function line(): array
    {
        return PlatformWhatsApp::lastKnown();
    }

    /** Green with no date is a claim; green with one is a measurement. */
    #[Computed]
    public function verifiedAgo(): ?string
    {
        return $this->line['at']?->diffForHumans();
    }

    #[Computed]
    public function brand(): string
    {
        return Company::brand();
    }

    /** The line's screen just asked the bridge: take its answer now, not at the next beat. */
    #[On('platform-whatsapp:read')]
    public function refreshLine(): void
    {
        unset($this->line, $this->verifiedAgo);
    }

    public function allowed(): bool
    {
        return Auth::user()?->can('integrations.view') === true;
    }
};
?>

<div class="conn-pill-slot" wire:poll.30s.visible>
    @if ($this->allowed())
        @if ($this->line['state'] === PlatformWhatsAppState::Connected)
            <a
                href="{{ route('admin.platform-whatsapp') }}"
                wire:navigate
                class="conn-pill conn-pill-link"
                data-testid="platform-pill-connected"
                @if ($this->verifiedAgo !== null) title="{{ __('platform_whatsapp.topbar.verified_ago', ['ago' => $this->verifiedAgo]) }}" @endif
            >
                <span class="conn-dot"></span>{{ __('platform_whatsapp.topbar.connected', ['brand' => $this->brand]) }}
            </a>
        @else
            <a
                href="{{ route('admin.platform-whatsapp') }}"
                wire:navigate
                class="conn-pill conn-pill-warning"
                data-testid="platform-pill-{{ $this->line['state'] === PlatformWhatsAppState::Unreachable ? 'unverified' : 'down' }}"
            >
                <span class="conn-dot"></span>
                {{ __($this->line['state'] === PlatformWhatsAppState::Unreachable ? 'platform_whatsapp.topbar.unverified' : 'platform_whatsapp.topbar.disconnected', ['brand' => $this->brand]) }}
                @if ($this->line['since'] !== null)
                    · <span class="font-mono" data-testid="platform-pill-since">{{ $this->line['since']->diffForHumans(syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE, short: true) }}</span>
                @endif
            </a>
        @endif
    @endif
</div>
