<?php

use App\Actions\Business\StartWhatsAppLink;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Models\Business;
use App\Services\EvolutionApi;
use App\Traits\HasNotifications;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * "WhatsApp" — linking the number the assistant answers through. One card,
 * three states: explain-and-connect, live QR (polled: the code rotates
 * server-side and the connection webhook flips the column), connected.
 */
new class extends Component
{
    use HasNotifications;

    public bool $linking = false;

    public ?string $qr = null;

    #[Computed]
    public function business(): ?Business
    {
        return Auth::user()?->business;
    }

    #[Computed]
    public function connected(): bool
    {
        return $this->business?->isConnected() ?? false;
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
     * The poll while the QR is on screen. Connection is read from OUR
     * column — the webhook stamps it — so a dead bridge cannot fake an
     * "already linked". A failed QR refresh keeps the current code: the
     * next tick retries.
     */
    public function checkLink(): void
    {
        $business = $this->business?->refresh();

        if ($business === null) {
            return;
        }

        if ($business->isConnected()) {
            $this->linking = false;
            $this->qr = null;
            unset($this->business, $this->connected);
            $this->dispatchNotification(new NotificationDto(__('whatsapp.connect.done'), NotificationType::Success));

            return;
        }

        rescue(function () use ($business): void {
            $this->qr = app(EvolutionApi::class)->qrCode((string) $business->whatsapp_instance);
        }, report: false);
    }

    public function cancel(): void
    {
        $this->linking = false;
        $this->qr = null;
    }

    /** The tab title comes from translations; a PHP attribute cannot call __(). */
    public function render(): View
    {
        return $this->view()->title(__('whatsapp.title'));
    }
};
?>

<div>
    <div class="page-head">
        <div>
            <h1 class="page-head-title">{{ __('whatsapp.title') }}</h1>
            <p class="page-head-sub">{{ __('whatsapp.sub') }}</p>
        </div>
    </div>

    @if ($this->business === null)
        <x-ui.card class="max-w-2xl p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <p class="text-body">{{ __('whatsapp.no_business') }}</p>
                <x-ui.button variant="primary" size="sm" :href="route('onboarding')" wire:navigate>
                    {{ __('whatsapp.no_business_cta') }}
                </x-ui.button>
            </div>
        </x-ui.card>
    @elseif ($this->connected)
        <x-ui.card class="max-w-2xl p-6">
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
                        <span class="font-mono">{{ $this->business->whatsapp_connected_at?->format('d/m/Y H:i') }}</span>
                    </p>
                </div>
            </div>
        </x-ui.card>
    @else
        <x-ui.card class="p-6">
            <div class="grid items-center gap-8 lg:grid-cols-2">
                <div class="space-y-4">
                    <h2 class="font-display text-strong text-lg">{{ __('whatsapp.connect.title') }}</h2>
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
                            class="bg-brand-soft flex size-56 items-center justify-center rounded-[28px]"
                            style="color: var(--brand)"
                        >
                            <x-icon name="whatsapp" :size="72" />
                        </div>
                    @endif
                </div>
            </div>
        </x-ui.card>
    @endif
</div>
