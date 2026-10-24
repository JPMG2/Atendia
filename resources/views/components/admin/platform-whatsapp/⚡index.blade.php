<?php

use App\Actions\Admin\ReadPlatformWhatsApp;
use App\Actions\Admin\StartPlatformWhatsAppLink;
use App\Actions\Admin\UnlinkPlatformWhatsApp;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Models\Company;
use App\Traits\HasNotifications;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Livewire\Component;

/**
 * The platform's own WhatsApp line: what it is doing and the QR that links it.
 * It is read AFTER the first paint (`wire:init`): the bridge can be slow and
 * must never hold the page hostage. Linking and unlinking are the Actions'
 * to authorize; hiding a button here is only courtesy.
 */
new class extends Component
{
    use HasNotifications;

    /** Seconds a QR is shown before a fresh one is asked for: the bridge rotates it about every 45. */
    private const int QR_SECONDS = 40;

    /** @var array{instance: string, state: string, number: ?string, since: ?string, reason: ?string, checked_at: string}|null */
    public ?array $line = null;

    /** The pairing QR on screen, as a data URI, and the second it was asked for. */
    public ?string $qr = null;

    public ?int $qrAt = null;

    /** Asks the bridge, now. Runs on first paint, on the poll and on "Comprobar ahora". */
    public function check(): void
    {
        $line = app(ReadPlatformWhatsApp::class)->handle();
        $local = Company::localTimezone();

        $this->line = [
            'instance' => $line->instance,
            'state' => $line->state->value,
            'number' => $line->number,
            'since' => $line->since?->setTimezone($local)->format('d/m H:i'),
            'reason' => $line->reason,
            'checked_at' => $line->checkedAt->setTimezone($local)->format('d/m H:i'),
        ];

        if ($line->state->isConnected()) {
            if ($this->qr !== null) {
                $this->dispatchNotification(new NotificationDto(__('platform_whatsapp.link.linked'), NotificationType::Success));
            }

            $this->qr = null;
            $this->qrAt = null;

            return;
        }

        if ($this->qr !== null && time() - (int) $this->qrAt >= self::QR_SECONDS) {
            $this->link();
        }
    }

    public function link(): void
    {
        try {
            $this->qr = app(StartPlatformWhatsAppLink::class)->handle();
            $this->qrAt = time();
        } catch (ConnectionException|RequestException|RuntimeException) {
            $this->qr = null;
            $this->qrAt = null;
            $this->dispatchNotification(new NotificationDto(__('platform_whatsapp.link.failed'), NotificationType::Error));

            return;
        }

        $this->check();
    }

    public function unlink(): void
    {
        try {
            app(UnlinkPlatformWhatsApp::class)->handle();
        } catch (ConnectionException|RuntimeException) {
            $this->dispatchNotification(new NotificationDto(__('platform_whatsapp.unlink.failed'), NotificationType::Error));

            return;
        }

        $this->qr = null;
        $this->qrAt = null;
        $this->dispatchNotification(new NotificationDto(__('platform_whatsapp.unlink.done'), NotificationType::Success));
        $this->check();
    }

    /** The tab title comes from translations; a PHP attribute cannot call __(). */
    public function render(): View
    {
        return $this->view()->title(__('platform_whatsapp.title'));
    }
};
?>

@php
    $state = $line['state'] ?? null;
    $tone = ['connected' => 'success', 'pairing' => 'warning', 'unreachable' => 'warning'][$state] ?? 'danger';
    $connected = $state === 'connected';
    $canLink = in_array($state, ['pairing', 'closed', 'missing'], true);
    $note = match ($state) {
        'connected' => 'connected',
        'unreachable' => 'unreachable',
        'not_configured' => 'not_configured',
        default => 'down',
    };
@endphp

<div wire:init="check">
    {{-- While a QR is on screen the line is asked about every few seconds, so "linked" shows by itself; otherwise it is a slow beat. --}}
    @if ($qr !== null)
        <div wire:poll.5s.visible="check"></div>
    @elseif ($line !== null)
        <div wire:poll.30s.visible="check"></div>
    @endif

    <x-ui.page-head class="pw-bar" :title="__('platform_whatsapp.title')" :sub="__('platform_whatsapp.subtitle')">
        <x-ui.button variant="secondary" icon="refresh-cw" wire:click="check" wire:loading.attr="disabled" wire:target="check">
            {{ __('platform_whatsapp.check') }}
        </x-ui.button>
    </x-ui.page-head>

    <div class="pw-grid">
        <div class="pw-col">

            <x-ui.card class="pw-card">
                <div class="pw-head">
                    <h2 class="pw-title">{{ __('platform_whatsapp.status.title') }}</h2>

                    @if ($state === null)
                        <span class="status-tag is-wait"><span class="dot"></span>{{ __('platform_whatsapp.status.checking') }}</span>
                    @else
                        <span class="status-tag is-{{ $tone }}"><span class="dot"></span>{{ __('platform_whatsapp.status.'.$state) }}</span>
                    @endif
                </div>

                @if ($line !== null)
                    <dl class="pw-facts">
                        <dt>{{ __('platform_whatsapp.rows.number') }}</dt>
                        <dd class="font-mono">{{ $line['number'] !== null ? '+'.$line['number'] : __('platform_whatsapp.rows.number_none') }}</dd>

                        @if ($line['instance'] !== '')
                            <dt>{{ __('platform_whatsapp.rows.instance') }}</dt>
                            <dd class="font-mono">{{ $line['instance'] }}</dd>
                        @endif

                        <dt>{{ __('platform_whatsapp.rows.checked') }}</dt>
                        <dd class="font-mono">{{ $line['checked_at'] }}</dd>

                        @if ($line['since'] !== null)
                            <dt>{{ $connected ? __('platform_whatsapp.rows.since_connected') : __('platform_whatsapp.rows.since_closed') }}</dt>
                            <dd>
                                <span class="font-mono">{{ $line['since'] }}</span>
                                @if (! $connected && $line['reason'] !== null)
                                    · {{ __('platform_whatsapp.reason.'.(in_array($line['reason'], ['device_removed'], true) ? $line['reason'] : 'other')) }}
                                @endif
                            </dd>
                        @endif
                    </dl>

                    <x-ui.alert :variant="$connected ? 'success' : 'warning'" :icon="$connected ? 'check-circle' : 'triangle-alert'">
                        {{ __('platform_whatsapp.notes.'.$note) }}
                    </x-ui.alert>
                @endif
            </x-ui.card>

            @can('integrations.manage')
                @if ($canLink)
                    <x-ui.card class="pw-card">
                        <div>
                            <h2 class="pw-title">{{ __('platform_whatsapp.link.title') }}</h2>
                            <p class="pw-sub">{{ __('platform_whatsapp.link.sub') }}</p>
                        </div>

                        @if ($qr !== null)
                            <div class="pw-pair">
                                <div class="pw-qr-box" wire:key="qr-{{ $qrAt }}" x-data="{ left: {{ max(0, 40 - (time() - (int) $qrAt)) }} }" x-init="setInterval(() => left = Math.max(0, left - 1), 1000)">
                                    <img class="pw-qr" src="{{ $qr }}" alt="{{ __('platform_whatsapp.link.qr_alt') }}" width="200" height="200">
                                    <span class="pw-renews">{{ __('platform_whatsapp.link.renews') }} <span class="font-mono" x-text="left + ' s'"></span></span>
                                </div>

                                <ol class="pw-steps">
                                    <li><span class="pw-step-n">1</span>{{ $line['number'] !== null ? __('platform_whatsapp.link.steps.open_number', ['number' => '+'.$line['number']]) : __('platform_whatsapp.link.steps.open') }}</li>
                                    <li><span class="pw-step-n">2</span>{{ __('platform_whatsapp.link.steps.devices') }}</li>
                                    <li><span class="pw-step-n">3</span>{{ __('platform_whatsapp.link.steps.scan') }}</li>
                                </ol>
                            </div>

                            <div>
                                <x-ui.button wire:click="link" wire:loading.attr="disabled" wire:target="link">{{ __('platform_whatsapp.link.again') }}</x-ui.button>
                            </div>
                        @else
                            <div>
                                <x-ui.button icon="whatsapp" wire:click="link" wire:loading.attr="disabled" wire:target="link">{{ __('platform_whatsapp.link.start') }}</x-ui.button>
                            </div>
                        @endif
                    </x-ui.card>
                @endif

                @if ($connected)
                    <x-ui.card class="pw-card">
                        <div>
                            <h2 class="pw-title">{{ __('platform_whatsapp.unlink.title') }}</h2>
                            <p class="pw-sub">{{ __('platform_whatsapp.unlink.body') }}</p>
                        </div>
                        <div>
                            <x-ui.button
                                variant="danger"
                                x-on:click="dialog.confirm({
                                    title: {{ \Illuminate\Support\Js::from(__('platform_whatsapp.unlink.confirm_title')) }},
                                    message: {{ \Illuminate\Support\Js::from(__('platform_whatsapp.unlink.confirm_message')) }},
                                    accept: {{ \Illuminate\Support\Js::from(__('platform_whatsapp.unlink.confirm_accept')) }},
                                    type: 'danger',
                                }).then((ok) => { if (ok) { $wire.unlink(); } })"
                            >
                                {{ __('platform_whatsapp.unlink.action') }}
                            </x-ui.button>
                        </div>
                    </x-ui.card>
                @endif
            @endcan
        </div>

        <div class="pw-col">
            <x-ui.card class="pw-card pw-uses">
                <h2 class="pw-title">{{ __('platform_whatsapp.uses.title') }}</h2>

                @foreach ([['lock', 'codes'], ['bell', 'alerts'], ['file-text', 'digest']] as [$icon, $key])
                    <div class="pw-use">
                        <span class="pw-use-ico"><x-icon :name="$icon" :size="20" /></span>
                        <div>
                            <b>{{ __('platform_whatsapp.uses.'.$key) }}</b>
                            <span>{{ __('platform_whatsapp.uses.'.$key.'_body') }}</span>
                        </div>
                    </div>
                @endforeach
            </x-ui.card>

            <div class="pw-guard">
                <x-icon name="shield-check" :size="22" />
                <div>
                    <b>{{ __('platform_whatsapp.guard.title') }}</b>
                    <span>{{ __('platform_whatsapp.guard.body') }}</span>
                </div>
            </div>
        </div>
    </div>
</div>
