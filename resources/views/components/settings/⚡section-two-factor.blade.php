<?php

use App\Livewire\Forms\Settings\TwoFactorForm;
use App\Traits\HasNotifications;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Two-step verification card of "Ajustes". Three states: no number to send
 * to, off (prove the number with a code to turn it on) and on.
 */
new class extends Component
{
    use HasNotifications;

    public TwoFactorForm $form;

    public bool $codeSent = false;

    public function mount(): void
    {
        $this->form->setup();
    }

    public function sendCode(): void
    {
        $this->dispatchNotification($this->form->sendCode());
        $this->codeSent = true;
    }

    public function activate(): void
    {
        $this->dispatchNotification($this->form->activate());
        $this->codeSent = false;
        unset($this->isEnabled);
    }

    public function regenerateCodes(): void
    {
        $this->dispatchNotification($this->form->regenerateCodes());
    }

    #[Computed]
    public function codesLeft(): int
    {
        return Auth::user()->recoveryCodesLeft();
    }

    public function disable(): void
    {
        $this->dispatchNotification($this->form->disable());
        unset($this->isEnabled);
    }

    #[Computed]
    public function phone(): ?string
    {
        return Auth::user()->maskedSecondFactorPhone();
    }

    #[Computed]
    public function isEnabled(): bool
    {
        return Auth::user()->refresh()->sendsLoginCodesByWhatsApp();
    }
};
?>

<x-ui.card id="dos-pasos" class="bp-card">
    <div class="bp-card-head">
        <h2>{{ __('settings.two_factor.title') }}</h2>
        @if ($this->isEnabled)
            <span class="status-tag is-brand"><x-icon name="shield-check" :size="12" />{{ __('settings.two_factor.active') }}</span>
        @else
            <span class="status-tag is-neutral">{{ __('settings.two_factor.inactive') }}</span>
        @endif
    </div>
    <p class="bp-card-sub">{{ __('settings.two_factor.sub') }}</p>

    @if ($this->phone === null)
        <div class="st-pending" role="status">
            <x-icon name="message-circle" :size="18" />
            <div>
                <p>{{ __('settings.two_factor.no_phone') }}</p>
                <div class="st-pending-links">
                    <a href="{{ route('my-business.contacto') }}" wire:navigate class="st-link">
                        {{ __('settings.two_factor.go_contact') }}
                    </a>
                </div>
            </div>
        </div>
    @elseif ($form->recovery_codes !== [])
        {{-- Shown once: only hashes are stored, so this is the one chance to keep them. --}}
        <div class="st-codes" x-data="{ copied: false }">
            <p class="st-codes-warn">
                <x-icon name="triangle-alert" :size="16" />{{ __('settings.two_factor.codes_warning') }}
            </p>
            <ul class="st-codes-grid font-mono">
                @foreach ($form->recovery_codes as $recoveryCode)
                    <li>{{ $recoveryCode }}</li>
                @endforeach
            </ul>
            <div class="bp-card-actions">
                <x-ui.button
                    variant="secondary"
                    size="sm"
                    icon="copy"
                    x-on:click="navigator.clipboard.writeText({{ \Illuminate\Support\Js::from(implode(PHP_EOL, $form->recovery_codes)) }}).then(() => (copied = true))"
                >
                    <span x-show="! copied">{{ __('settings.two_factor.codes_copy') }}</span>
                    <span x-show="copied" x-cloak>{{ __('settings.two_factor.codes_copied') }}</span>
                </x-ui.button>
                <x-ui.button variant="primary" size="sm" wire:click="$set('form.recovery_codes', [])">
                    {{ __('settings.two_factor.codes_done') }}</x-ui.button>
            </div>
        </div>
    @elseif ($this->isEnabled)
        <p class="st-current">
            <x-icon name="message-circle" :size="16" />
            <span>{{ __('settings.two_factor.active_detail', ['phone' => $this->phone]) }}</span>
        </p>
        <p class="st-current">
            <x-icon name="key-round" :size="16" />
            <span>{{ trans_choice('settings.two_factor.codes_left', $this->codesLeft, ['count' => $this->codesLeft]) }}</span>
        </p>

        <x-catalog.form-row>
            <x-inputsform.input
                span="long"
                type="password"
                name="current_password"
                wire:model="form.current_password"
                :label="__('settings.current_password')"
                autocomplete="current-password"
            />
        </x-catalog.form-row>

        <div class="bp-card-actions">
            <x-ui.button variant="secondary" size="sm" wire:click="regenerateCodes">
                {{ __('settings.two_factor.codes_regenerate') }}</x-ui.button>
            <x-ui.button variant="danger" size="sm" wire:click="disable">
                {{ __('settings.two_factor.disable') }}</x-ui.button>
        </div>
    @elseif ($codeSent)
        <p class="st-current">
            <x-icon name="message-circle" :size="16" />
            <span>{{ __('settings.two_factor.target', ['phone' => $this->phone]) }}</span>
        </p>

        <x-catalog.form-row>
            <x-inputsform.input
                span="short"
                name="code"
                class="font-mono"
                inputmode="numeric"
                maxlength="6"
                placeholder="000000"
                autocomplete="one-time-code"
                wire:model="form.code"
                :label="__('settings.two_factor.code')"
            />
        </x-catalog.form-row>

        <div class="bp-card-actions">
            <button type="button" class="st-link is-muted" wire:click="$set('codeSent', false)">
                {{ __('settings.two_factor.send') }}
            </button>
            <x-ui.button variant="primary" size="sm" wire:click="activate">
                {{ __('settings.two_factor.confirm') }}</x-ui.button>
        </div>
    @else
        <p class="st-current">
            <x-icon name="message-circle" :size="16" />
            <span>{{ __('settings.two_factor.target', ['phone' => $this->phone]) }}</span>
        </p>

        <x-catalog.form-row>
            <x-inputsform.input
                span="long"
                type="password"
                name="current_password"
                wire:model="form.current_password"
                :label="__('settings.current_password')"
                autocomplete="current-password"
            />
        </x-catalog.form-row>

        <div class="bp-card-actions">
            <x-ui.button variant="primary" size="sm" icon="message-circle" wire:click="sendCode">
                {{ __('settings.two_factor.send') }}</x-ui.button>
        </div>
    @endif
</x-ui.card>
