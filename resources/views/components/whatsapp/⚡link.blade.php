<?php

use App\Actions\Business\StartWhatsAppLink;
use App\Actions\Business\VerifyWhatsAppLink;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Enums\WhatsAppLinkState;
use App\Models\Business;
use App\Models\WhatsAppLinkEvent;
use App\Services\EvolutionApi;
use App\Traits\HasNotifications;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The linking card, shared by the panel screen and wizard step 5 so both
 * tell the same story. Four states: connect, live QR, connected, and the
 * one that keeps it honest — bridge silent, so we say we do not know
 * instead of repeating a stamp nobody confirmed.
 */
new class extends Component
{
    use HasNotifications;

    public bool $linking = false;

    public ?string $qr = null;

    public WhatsAppLinkState $state = WhatsAppLinkState::Disconnected;

    /** The probe runs here and on each poll tick, never on every render: in the wizard this component stays mounted behind the other steps. */
    public function mount(): void
    {
        $this->state = $this->verify();

        // Arriving from the "it fell" notice: the QR is what she came for,
        // so it is waiting instead of one more button to find.
        if (request()->boolean('conectar')) {
            $this->connect();
        }
    }

    #[Computed]
    public function business(): ?Business
    {
        return Auth::user()?->business;
    }

    #[Computed]
    public function connected(): bool
    {
        return $this->state === WhatsAppLinkState::Connected;
    }

    public function connect(): void
    {
        $business = $this->business;

        if ($business === null || $this->connected) {
            return;
        }

        try {
            $this->qr = app(StartWhatsAppLink::class)->handle($business);
        } catch (Throwable $e) {
            report($e);
            $this->dispatchNotification(new NotificationDto(__('whatsapp.connect.failed'), NotificationType::Error));

            return;
        }

        unset($this->business);
        $this->linking = true;
    }

    /**
     * The poll while the QR is on screen. It asks the BRIDGE, so linking no
     * longer depends on the connection webhook arriving. A failed QR refresh
     * keeps the current code: the next tick retries.
     */
    public function checkLink(): void
    {
        $business = $this->business?->refresh();

        if ($business === null) {
            return;
        }

        $this->state = $this->verify();

        if ($this->connected) {
            $this->linking = false;
            $this->qr = null;
            unset($this->business, $this->connected);
            $this->dispatchNotification(new NotificationDto(__('whatsapp.connect.done'), NotificationType::Success));
            $this->dispatch('whatsapp:connected');

            return;
        }

        rescue(function () use ($business): void {
            $this->qr = app(EvolutionApi::class)->qrCode((string) $business->whatsapp_instance);
        }, report: false);
    }

    /**
     * Who the linked phone says it is. The model holds it: the topbar pill
     * greets the same number the moment it pairs.
     *
     * @return array{number: string, name: string, picture: ?string}|null
     */
    #[Computed]
    public function profile(): ?array
    {
        return $this->connected ? $this->business?->whatsAppProfile() : null;
    }

    /**
     * How the line has behaved lately. Only shown once linked: on a screen
     * that still says "conectá tu número" a reliability figure is noise.
     *
     * @return array{days: int, since: CarbonImmutable, outages: int, downMinutes: int, lastFall: ?CarbonImmutable, lastReason: ?string}|null
     */
    #[Computed]
    public function reliability(): ?array
    {
        $business = $this->business;

        return $this->connected && $business !== null
            ? WhatsAppLinkEvent::reliability($business)
            : null;
    }

    /** The retry behind the "we could not check" card. */
    public function recheck(): void
    {
        unset($this->business, $this->connected);

        $this->state = $this->verify();
    }

    public function cancel(): void
    {
        $this->linking = false;
        $this->qr = null;
    }

    private function verify(): WhatsAppLinkState
    {
        $business = $this->business;

        return $business === null
            ? WhatsAppLinkState::Disconnected
            : app(VerifyWhatsAppLink::class)->handle($business);
    }
};
?>

<div data-testid="whatsapp-link">
    @if ($this->business === null)
        <x-ui.card class="p-5">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <p class="text-body">{{ __('whatsapp.no_business') }}</p>
                <x-ui.button variant="primary" size="sm" :href="route('onboarding')" wire:navigate>
                    {{ __('whatsapp.no_business_cta') }}
                </x-ui.button>
            </div>
        </x-ui.card>
    @elseif ($this->connected)
        <x-ui.card class="p-5">
            <div class="flex items-start gap-4">
                <span
                    class="bg-brand-soft flex size-12 flex-none items-center justify-center rounded-2xl"
                    style="color: var(--brand)"
                >
                    <x-icon name="whatsapp" :size="24" />
                </span>
                <div class="min-w-0 space-y-2">
                    <span class="status-tag is-success">
                        <span class="dot"></span>
                        {{ __('whatsapp.connected.tag') }}
                    </span>
                    <h2 class="font-display text-strong text-lg">{{ __('whatsapp.connected.title') }}</h2>
                    <p class="text-body text-sm">{{ __('whatsapp.connected.body') }}</p>
                    <p class="text-muted text-sm">
                        {{ __('whatsapp.connected.since') }}
                        <span class="font-mono">{{ $this->business->whatsapp_connected_at?->inBusinessTime()->format('d/m/Y H:i') }}</span>
                    </p>

                    {{-- The identity check, where the warning about linking a
                    personal line was: a name and a number beat a green tick. --}}
                    @if ($this->profile)
                        <div class="bg-sunken flex items-center gap-3 rounded-xl p-3">
                            <x-ui.avatar :src="$this->profile['picture']" :name="$this->profile['name'] ?: $this->business->name" size="sm" />
                            <div class="min-w-0">
                                <p class="text-strong text-sm font-semibold">{{ $this->profile['name'] ?: __('whatsapp.connected.no_profile_name') }}</p>
                                <p class="text-muted font-mono text-xs">+{{ $this->profile['number'] }}</p>
                            </div>
                        </div>
                        <p class="text-subtle text-xs">{{ __('whatsapp.connected.identity_hint') }}</p>
                    @endif
                </div>
            </div>

            {{-- "Connected" is a snapshot; this is the film. A line that did
            not drop all week is what a business is actually paying for. --}}
            @if ($this->reliability)
                <div class="bd-subtle mt-4 border-t pt-4">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <h3 class="font-display text-strong text-sm">
                            {{ __('whatsapp.history.title', ['days' => $this->reliability['days']]) }}
                        </h3>
                        <span class="text-subtle text-xs">
                            {{ __('whatsapp.history.since', ['date' => $this->reliability['since']->inBusinessTime()->format('d/m/Y')]) }}
                        </span>
                    </div>

                    @if ($this->reliability['outages'] === 0)
                        <p class="text-body mt-2 flex items-center gap-2 text-sm">
                            <span class="flex-none" style="color: var(--success)"><x-icon name="check" :size="16" /></span>
                            {{ __('whatsapp.history.clean') }}
                        </p>
                    @else
                        {{-- Mono carries the figures only; a whole sentence in
                        mono stops reading like a sentence. --}}
                        <p class="text-body mt-2 text-sm">
                            {{ __('whatsapp.history.outages') }}
                            <span class="text-strong font-mono">{{ trans_choice('whatsapp.history.times', $this->reliability['outages'], ['count' => $this->reliability['outages']]) }}</span>,
                            {{ __('whatsapp.history.down') }}
                            <span class="text-strong font-mono">{{ $this->reliability['downMinutes'] >= 60
                                ? trans_choice('whatsapp.history.hours', intdiv($this->reliability['downMinutes'], 60), ['count' => intdiv($this->reliability['downMinutes'], 60)])
                                : trans_choice('whatsapp.history.minutes', $this->reliability['downMinutes'], ['count' => $this->reliability['downMinutes']]) }}</span>.
                        </p>
                        @if ($this->reliability['lastReason'] === 'device_removed')
                            <p class="text-muted mt-1 text-xs">{{ __('whatsapp.history.device_removed') }}</p>
                        @endif
                    @endif
                </div>
            @endif
        </x-ui.card>
    @elseif ($state === App\Enums\WhatsAppLinkState::Unverified)
        {{-- Not connected and not disconnected: the bridge stayed silent. Saying
        so beats repeating a stamp nobody confirmed. --}}
        <x-ui.card class="p-5">
            <div class="flex items-start gap-4">
                <span
                    class="flex size-12 flex-none items-center justify-center rounded-2xl"
                    style="background: var(--warning-soft); color: var(--warning)"
                >
                    <x-icon name="triangle-alert" :size="24" />
                </span>
                <div class="min-w-0 space-y-2">
                    <span class="status-tag is-warning">
                        <span class="dot"></span>
                        {{ __('whatsapp.unverified.tag') }}
                    </span>
                    <h2 class="font-display text-strong text-lg">{{ __('whatsapp.unverified.title') }}</h2>
                    <p class="text-body text-sm">{{ __('whatsapp.unverified.body') }}</p>
                    <x-ui.button variant="secondary" size="sm" icon="refresh-cw" wire:click="recheck">
                        {{ __('whatsapp.unverified.retry') }}
                    </x-ui.button>
                </div>
            </div>
        </x-ui.card>
    @else
        <x-ui.card class="p-5">
            <div class="space-y-4">
            {{-- The consequence, not the rule: "it will answer your family"
            convinces where "best practice" does not. Born from a real ban. --}}
            <div
                class="flex items-start gap-3 rounded-2xl border-2 p-3"
                style="border-color: var(--warning); background: var(--warning-soft)"
            >
                <span
                    class="warn-beacon flex size-10 flex-none items-center justify-center rounded-xl"
                    style="background: var(--warning); color: var(--text-on-brand)"
                >
                    <x-icon name="triangle-alert" :size="20" />
                </span>
                <div class="min-w-0">
                    <h3 class="font-display text-strong text-base">{{ __('whatsapp.connect.dedicated_title') }}</h3>
                    <p class="text-body mt-0.5 text-sm">{{ __('whatsapp.connect.dedicated_body') }}</p>
                </div>
            </div>

            <div class="grid items-center gap-6 lg:grid-cols-[1.6fr_1fr]">
                <div class="space-y-3">
                    <h2 class="font-display text-strong text-xl">{{ __('whatsapp.connect.title') }}</h2>
                    <p class="text-body text-sm">{{ __('whatsapp.connect.body') }}</p>

                    <ol class="space-y-2">
                        @foreach (__('whatsapp.connect.steps') as $index => $step)
                            <li class="flex items-center gap-3">
                                <span
                                    class="bg-brand-soft flex size-6 flex-none items-center justify-center rounded-full font-mono text-xs"
                                    style="color: var(--brand-soft-text)"
                                >{{ $index + 1 }}</span>
                                <span class="text-body text-sm">{{ $step }}</span>
                            </li>
                        @endforeach
                    </ol>

                    {{-- The aged-number advice sold as expertise: the caution is
                    WhatsApp's nature, never Atendia's limitation. --}}
                    <p class="bg-brand-soft flex items-start gap-2 rounded-xl p-2.5 text-sm">
                        <span class="mt-0.5 flex-none" style="color: var(--brand)"><x-icon name="sparkles" :size="16" /></span>
                        <span class="text-body"><strong class="text-strong">{{ __('whatsapp.connect.tip_label') }}:</strong>
                            {{ __('whatsapp.connect.tip') }}</span>
                    </p>

                    @unless ($linking)
                        <x-ui.button variant="primary" icon="whatsapp" wire:click="connect">
                            {{ __('whatsapp.connect.cta') }}
                        </x-ui.button>
                    @endunless
                </div>

                <div class="flex justify-center">
                    @if ($linking)
                        <div wire:poll.4s="checkLink" class="flex flex-col items-center gap-3">
                            @if ($qr)
                                {{-- The PNG carries its own light backdrop: WhatsApp
                                needs a light code even in dark mode. --}}
                                <img
                                    src="{{ $qr }}"
                                    alt="{{ __('whatsapp.connect.qr_alt') }}"
                                    class="bd-subtle size-56 rounded-2xl border"
                                />
                            @else
                                <div class="bg-sunken bd-subtle flex size-56 items-center justify-center rounded-[28px] border">
                                    <span class="text-muted text-sm">{{ __('whatsapp.connect.waiting') }}</span>
                                </div>
                            @endif
                            <p class="text-muted text-sm">{{ __('whatsapp.connect.qr_hint') }}</p>
                            <x-ui.button variant="danger" size="sm" wire:click="cancel">
                                {{ __('whatsapp.connect.cancel') }}
                            </x-ui.button>
                        </div>
                    @else
                        <div
                            class="bg-brand-soft flex size-44 items-center justify-center rounded-[28px]"
                            style="color: var(--brand)"
                        >
                            <x-icon name="whatsapp" :size="56" />
                        </div>
                    @endif
                </div>
            </div>

            <p class="text-muted flex items-start gap-2 text-xs">
                <span class="mt-0.5 flex-none"><x-icon name="lock" :size="14" /></span>
                {{ __('whatsapp.connect.privacy') }}
            </p>
            </div>
        </x-ui.card>
    @endif
</div>
