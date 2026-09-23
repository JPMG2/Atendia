<?php

use App\Livewire\Forms\Settings\PasswordForm;
use App\Traits\HasNotifications;
use Livewire\Component;

/**
 * Password card of "Ajustes". {@see PasswordForm} re-authenticates, applies
 * the app-wide policy and mails the receipt.
 */
new class extends Component
{
    use HasNotifications;

    public PasswordForm $form;

    public function mount(): void
    {
        $this->form->setup();
    }

    public function save(): void
    {
        $this->dispatchNotification($this->form->save());
    }
};
?>

<x-ui.card id="contrasena" class="bp-card" x-data="settingsPasswordForm">
    <div class="bp-card-head">
        <h2>{{ __('settings.password.title') }}</h2>
    </div>
    <p class="bp-card-sub">{{ __('settings.password.sub') }}</p>

    <x-catalog.form-row>
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
        <x-inputsform.input
            span="text"
            type="password"
            name="password"
            alpine-error="password"
            wire:model="form.password"
            :label="__('settings.password.new')"
            autocomplete="new-password"
            required
        />
        <x-inputsform.input
            span="text"
            type="password"
            name="password_confirmation"
            alpine-error="password_confirmation"
            wire:model="form.password_confirmation"
            :label="__('settings.password.confirm')"
            autocomplete="new-password"
            required
        />
    </x-catalog.form-row>

    <div class="mt-3">
        <x-ui.checkbox name="logout_others" wire:model="form.logout_others" :label="__('settings.password.logout_others')" />
    </div>

    <div class="bp-card-actions">
        <span class="bp-card-note"><x-icon name="shield-check" :size="16" />{{ __('settings.password.notice') }}</span>
        <x-ui.button variant="primary" size="sm" x-on:click="submit()">{{ __('settings.password.submit') }}</x-ui.button>
    </div>
</x-ui.card>

@script
    <script>
        // FRONT half of the password policy: same checks the server runs, minus the breach lookup.
        Alpine.data('settingsPasswordForm', () => ({
            errors: {},
            path: 'form',

            async submit() {
                const values = {
                    current_password: this.$wire.get(`${this.path}.current_password`) ?? '',
                    password: this.$wire.get(`${this.path}.password`) ?? '',
                    password_confirmation: this.$wire.get(`${this.path}.password_confirmation`) ?? '',
                };

                this.errors = validate(values, {
                    current_password: ['required'],
                    password: ['required', ['minLength', 8], 'hasUpper', 'hasNumber', 'hasSymbol'],
                    password_confirmation: ['required', ['same', values.password]],
                });

                if (Object.keys(this.errors).length > 0) {
                    return;
                }

                await this.$wire.save();
            },
        }));
    </script>
@endscript
