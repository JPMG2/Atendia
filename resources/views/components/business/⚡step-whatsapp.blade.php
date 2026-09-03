<?php

use App\Enums\NotificationType;
use App\Livewire\Forms\Business\BusinessForm;
use App\Traits\HasNotifications;
use Livewire\Component;

/**
 * Wizard step 5 — the connection, wired to {@see BusinessForm}: the two
 * WhatsApp numbers (the one the AI answers on, and a human's phone for what
 * it cannot answer) plus the business email, where the welcome lands.
 * The QR is still a prop: nothing connects yet.
 */
new class extends Component {
    use HasNotifications;

    public BusinessForm $form;

    /** The DTO must exist before the first render: `setup()` is not a hook. */
    public function mount(): void
    {
        $this->form->setup();
    }

    /** Skipping saves nothing — "conectar después" is a promise kept. */
    public function finish(bool $skipped = false): void
    {
        if (! $skipped) {
            $notification = $this->form->saveConnection();

            $this->dispatchNotification($notification);

            if ($notification->type === NotificationType::Error) {
                return;
            }
        }

        $this->dispatch('wizard:step-completed', step: 5, skipped: $skipped, connected: ! $skipped);
    }
};
?>

<div>
    <h2>{{ __('wizard.steps.5.heading') }}</h2>
    <p class="lead">{{ __('wizard.steps.5.lead') }}</p>

    <x-ui.card>
        <div class="wizard-frow">
            <x-inputsform.input span="short" name="whatsapp_number" class="font-mono" wire:model="form.data.whatsapp_number"
                :label="__('wizard.fields.whatsapp_number')"
                :placeholder="__('wizard.fields.whatsapp_number_placeholder')"
                :hint="__('wizard.fields.whatsapp_number_hint')" />
            <x-inputsform.input span="short" name="fallback_whatsapp_number" class="font-mono" wire:model="form.data.fallback_whatsapp_number"
                :label="__('wizard.fields.fallback_whatsapp_number')"
                :placeholder="__('wizard.fields.fallback_whatsapp_number_placeholder')"
                :hint="__('wizard.fields.fallback_whatsapp_number_hint')" />

            <x-inputsform.input span="long" type="email" name="email" wire:model="form.data.email"
                :label="__('wizard.fields.business_email')"
                :placeholder="__('wizard.fields.business_email_placeholder')"
                :hint="__('wizard.fields.business_email_hint')" />
        </div>

        <div class="wizard-qrbox">
            {{-- The mock QR from the maqueta, not an icon: a QR plate stays
            light in dark mode, like the real one will. --}}
            <div class="wizard-qr" aria-label="Código QR de ejemplo">
                <svg viewBox="0 0 100 100" fill="currentColor"><rect x="4" y="4" width="26" height="26" rx="3"/><rect x="10" y="10" width="14" height="14" fill="var(--ink-0)"/><rect x="70" y="4" width="26" height="26" rx="3"/><rect x="76" y="10" width="14" height="14" fill="var(--ink-0)"/><rect x="4" y="70" width="26" height="26" rx="3"/><rect x="10" y="76" width="14" height="14" fill="var(--ink-0)"/><rect x="40" y="8" width="8" height="8"/><rect x="52" y="16" width="8" height="8"/><rect x="40" y="28" width="8" height="8"/><rect x="8" y="40" width="8" height="8"/><rect x="24" y="44" width="8" height="8"/><rect x="40" y="44" width="8" height="8"/><rect x="56" y="40" width="8" height="8"/><rect x="72" y="44" width="8" height="8"/><rect x="88" y="40" width="8" height="8"/><rect x="44" y="58" width="8" height="8"/><rect x="60" y="56" width="8" height="8"/><rect x="80" y="60" width="8" height="8"/><rect x="44" y="74" width="8" height="8"/><rect x="58" y="82" width="8" height="8"/><rect x="74" y="76" width="8" height="8"/><rect x="88" y="88" width="8" height="8"/></svg>
            </div>
            <ol class="wizard-qr-steps">
                <li>{!! __('wizard.whatsapp.qr_step_1') !!}</li>
                <li>{!! __('wizard.whatsapp.qr_step_2') !!}</li>
                <li>{!! __('wizard.whatsapp.qr_step_3') !!}</li>
            </ol>
        </div>

        <div class="wizard-foot">
            <x-ui.button variant="ghost" wire:click="finish(true)">
                {{ __('wizard.whatsapp.later') }}
            </x-ui.button>
            <span class="wizard-spacer"></span>
            <x-ui.button variant="primary" wire:click="finish">
                {{ __('wizard.whatsapp.scanned') }}
            </x-ui.button>
        </div>
    </x-ui.card>
</div>
