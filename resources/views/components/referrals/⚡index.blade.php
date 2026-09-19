<?php

use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Mail\ReferralLink;
use App\Messaging\Channels\Email;
use App\Models\Business;
use App\Traits\HasNotifications;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * "Gana con AtendIa" — the client's referral link, the double-sided deal
 * (Dropbox pattern: both ends win) and the running tally. The reward math
 * stays in config so the admin dashboard can manage the rules later.
 */
new class extends Component
{
    use HasNotifications;
    #[Computed]
    public function business(): ?Business
    {
        return Auth::user()?->business;
    }

    #[Computed]
    public function referralLink(): ?string
    {
        return $this->business?->referralLink();
    }

    #[Computed]
    public function referredCount(): int
    {
        return $this->business?->referredCount() ?? 0;
    }

    #[Computed]
    public function shareUrl(): ?string
    {
        if ($this->referralLink === null) {
            return null;
        }

        return 'https://wa.me/?text='.urlencode(__('referrals.share_text', [
            'days' => (int) config('atendia.referral.invited_trial_days'),
            'url' => $this->referralLink,
        ]));
    }

    /** Black-on-white on purpose, whatever the theme: a QR exists to be scanned. */
    #[Computed]
    public function qrSvg(): ?string
    {
        if ($this->referralLink === null) {
            return null;
        }

        $svg = (new Writer(new ImageRenderer(new RendererStyle(168, 0), new SvgImageBackEnd)))
            ->writeString($this->referralLink);

        // The XML prolog goes: this SVG embeds inline in the page. The regex
        // avoids a literal close-tag sequence, which would end the SFC block.
        return (string) preg_replace('/^<\?xml[^>]*>\s*/', '', $svg);
    }

    #[Computed]
    public function qrDownload(): ?string
    {
        return $this->qrSvg === null
            ? null
            : 'data:image/svg+xml;base64,'.base64_encode($this->qrSvg);
    }

    /** The Starlink touch: the link lands in the inbox, ready to forward. */
    public function emailLink(): void
    {
        $business = $this->business;

        if ($business === null) {
            return;
        }

        $address = $business->email ?? $business->billing_email;

        (new Email($business, [$address], ReferralLink::class))->send();

        $this->dispatchNotification(new NotificationDto(
            __('referrals.emailed', ['email' => $address]),
            NotificationType::Success,
        ));
    }

    /** The tab title comes from translations; a PHP attribute cannot call __(). */
    public function render(): View
    {
        return $this->view()->title(__('referrals.title'));
    }
};
?>

<div>
    <div class="page-head">
        <div>
            <h1 class="page-head-title">{{ __('referrals.title') }}</h1>
            <p class="page-head-sub">{{ __('referrals.sub') }}</p>
        </div>
        @if ($this->business?->isFoundingPartner())
            <x-ui.badge variant="accent" :dot="true">{{ __('referrals.founder') }}</x-ui.badge>
        @endif
    </div>

    @if ($this->business !== null)
        <div class="referral-grid">
            <x-ui.card class="p-6">
                <h2 class="referral-block-title">{{ __('referrals.link_title') }}</h2>

                <div
                    x-data="{
                        copied: false,
                        copy() {
                            navigator.clipboard.writeText(@js($this->referralLink)).then(() => {
                                this.copied = true;
                                setTimeout(() => (this.copied = false), 2000);
                            });
                        },
                    }"
                >
                    <p class="referral-link font-mono">{{ $this->referralLink }}</p>

                    <div class="mt-4 flex flex-wrap gap-3">
                        <x-ui.button variant="primary" size="sm" icon="check" x-show="copied" x-cloak>
                            {{ __('referrals.copied') }}
                        </x-ui.button>
                        <x-ui.button variant="primary" size="sm" @click="copy()" x-show="! copied">
                            {{ __('referrals.copy') }}
                        </x-ui.button>
                        <x-ui.button variant="secondary" size="sm" :href="$this->shareUrl" target="_blank" icon="whatsapp">
                            {{ __('referrals.share_whatsapp') }}
                        </x-ui.button>
                        <x-ui.button variant="secondary" size="sm" wire:click="emailLink" icon="at-sign">
                            {{ __('referrals.email_me') }}
                        </x-ui.button>
                    </div>
                </div>

                <div class="referral-count">
                    <span class="referral-count-number font-mono">{{ $this->referredCount }}</span>
                    <div>
                        <p class="referral-count-label">{{ __('referrals.count_title') }}</p>
                        <p class="referral-count-hint">{{ __('referrals.count_hint') }}</p>
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card class="p-6">
                <h2 class="referral-block-title">{{ __('referrals.how_title') }}</h2>
                <ol class="referral-steps">
                    <li>{{ __('referrals.how_share') }}</li>
                    <li>{{ __('referrals.how_invited', ['days' => (int) config('atendia.referral.invited_trial_days')]) }}</li>
                    <li>{{ __('referrals.how_reward', ['percent' => (int) config('atendia.referral.reward_percent')]) }}</li>
                    <li>{{ __('referrals.how_stack') }}</li>
                </ol>

                <div class="referral-qr-row">
                    <span class="referral-qr">{!! $this->qrSvg !!}</span>
                    <div>
                        <p class="referral-count-label">{{ __('referrals.qr_title') }}</p>
                        <p class="referral-count-hint">{{ __('referrals.qr_hint') }}</p>
                        <a
                            href="{{ $this->qrDownload }}"
                            download="gana-con-atendia.svg"
                            class="referral-qr-download"
                        >
                            {{ __('referrals.download_qr') }}
                        </a>
                    </div>
                </div>
            </x-ui.card>
        </div>
    @endif
</div>
