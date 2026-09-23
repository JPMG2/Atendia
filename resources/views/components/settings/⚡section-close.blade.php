<?php

use App\Enums\NotificationType;
use App\Livewire\Forms\Settings\CloseAccountForm;
use App\Traits\HasNotifications;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Danger zone of "Ajustes". A successful close ends the session right here:
 * the account is already soft-deleted, so the next request could not load it.
 */
new class extends Component
{
    use HasNotifications;

    public CloseAccountForm $form;

    public function mount(): void
    {
        $this->form->setup();
    }

    public function close(): void
    {
        $notification = $this->form->save();

        if ($notification->type !== NotificationType::Success) {
            $this->dispatchNotification($notification);

            return;
        }

        Auth::guard('web')->logout();
        session()->invalidate();
        session()->regenerateToken();

        $this->redirect('/');
    }
};
?>

<x-ui.card id="cuenta" class="bp-card bp-card-danger" x-data="settingsCloseForm">
    <div class="bp-card-head">
        <h2>{{ __('settings.close.title') }}</h2>
    </div>
    <p class="bp-card-sub">
        {{
            auth()->user()->business
                ? __('settings.close.sub', ['days' => config('atendia.account_restore_days')])
                : __('settings.close.sub_no_business', ['days' => config('atendia.account_restore_days')])
        }}
    </p>

    <x-catalog.form-row>
        <x-inputsform.input
            span="text"
            name="confirmation"
            alpine-error="confirmation"
            class="font-mono"
            wire:model="form.confirmation"
            :label="__('settings.close.confirmation', ['keyword' => __('settings.close.keyword')])"
            :placeholder="__('settings.close.keyword')"
            autocomplete="off"
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
        <x-ui.button variant="danger" size="sm" icon="trash-2" x-on:click="submit()">
            {{ __('settings.close.submit') }}</x-ui.button>
    </div>
</x-ui.card>

@script
    <script>
        // Typed word + password first, then the system dialog: three locks before anything closes.
        Alpine.data('settingsCloseForm', () => ({
            errors: {},
            path: 'form',

            async submit() {
                const values = {
                    confirmation: (this.$wire.get(`${this.path}.confirmation`) ?? '').trim(),
                    current_password: this.$wire.get(`${this.path}.current_password`) ?? '',
                };

                this.errors = validate(values, {
                    confirmation: ['required', ['same', @js(__('settings.close.keyword'))]],
                    current_password: ['required'],
                });

                if (Object.keys(this.errors).length > 0) {
                    return;
                }

                if (
                    !(await dialog.confirm({
                        title: @js(__('settings.close.confirm_title')),
                        message: @js(__('settings.close.confirm_message', ['days' => config('atendia.account_restore_days')])),
                        accept: @js(__('settings.close.confirm_accept')),
                        type: 'danger',
                    }))
                ) {
                    return;
                }

                await this.$wire.close();
            },
        }));
    </script>
@endscript
