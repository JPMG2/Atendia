<?php

use App\Dto\UserDto;
use App\Livewire\Forms\Settings\EmailForm;
use App\Traits\HasNotifications;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Login-email card of "Ajustes": shows the address in use and any pending
 * one, and requests a change. {@see EmailForm} holds the rules and flow.
 */
new class extends Component
{
    use HasNotifications;

    public EmailForm $form;

    public function mount(): void
    {
        $this->form->setup();
    }

    #[Computed]
    public function account(): UserDto
    {
        return UserDto::fromUser(Auth::user()->refresh());
    }

    public function save(): void
    {
        $this->dispatchNotification($this->form->save());
        unset($this->account);
    }

    public function resend(): void
    {
        $this->dispatchNotification($this->form->resend());
    }

    public function sendVerification(): void
    {
        $this->dispatchNotification($this->form->sendVerification());
    }

    public function cancelChange(): void
    {
        $this->dispatchNotification($this->form->cancel());
        unset($this->account);
    }
};
?>

<x-ui.card id="correo" class="bp-card" x-data="settingsEmailForm">
    <div class="bp-card-head">
        <h2>{{ __('settings.email.title') }}</h2>
        @if ($this->account->isVerified)
            <span class="status-tag is-brand"><x-icon name="check" :size="12" />{{ __('settings.email.verified') }}</span>
        @else
            <span class="status-tag is-warning">{{ __('settings.email.unverified') }}</span>
            <button type="button" class="st-link ml-auto" wire:click="sendVerification">
                {{ __('settings.email.verify') }}
            </button>
        @endif
    </div>
    <p class="bp-card-sub">{{ __('settings.email.sub') }}</p>

    @if ($this->account->pending_email)
        <div class="st-pending" role="status">
            <x-icon name="mail" :size="18" />
            <div>
                <p>{{ __('settings.email.pending', ['email' => $this->account->pending_email]) }}</p>
                <div class="st-pending-links">
                    <button type="button" class="st-link" wire:click="resend">{{ __('settings.email.resend') }}</button>
                    <button type="button" class="st-link is-muted" wire:click="cancelChange">
                        {{ __('settings.email.cancel') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- A line, not a readonly input: a long address wraps here instead of
    being clipped by a field's width. --}}
    <p class="st-current">
        <x-icon name="mail" :size="16" />
        <span>{{ __('settings.email.current') }}:</span>
        <b class="font-mono">{{ $this->account->email }}</b>
    </p>

    <x-catalog.form-row>
        <x-inputsform.input
            span="long"
            type="email"
            name="email"
            alpine-error="email"
            icon="mail"
            wire:model="form.email"
            :label="__('settings.email.new')"
            :placeholder="__('settings.email.new_placeholder')"
            autocomplete="email"
            required
        />
        <x-inputsform.input
            span="text"
            type="password"
            name="current_password"
            alpine-error="current_password"
            wire:model="form.current_password"
            :label="__('settings.current_password')"
            autocomplete="current-password"
            required
        />
    </x-catalog.form-row>

    <div class="bp-card-actions">
        <span class="bp-card-note"><x-icon name="shield-check" :size="16" />{{ __('settings.email.notice') }}</span>
        <x-ui.button variant="primary" size="sm" x-on:click="submit()">{{ __('settings.email.submit') }}</x-ui.button>
    </div>
</x-ui.card>

@script
    <script>
        // FRONT half of EmailForm's rules; the password is checked on the server only.
        Alpine.data('settingsEmailForm', () => ({
            errors: {},
            path: 'form',
            rules: {
                email: ['required', 'email', ['maxLength', 255]],
                current_password: ['required'],
            },

            async submit() {
                const values = {};

                for (const field in this.rules) {
                    values[field] = this.$wire.get(`${this.path}.${field}`) ?? '';
                }

                this.errors = validate(values, this.rules);

                if (Object.keys(this.errors).length > 0) {
                    return;
                }

                await this.$wire.save();
            },
        }));
    </script>
@endscript
