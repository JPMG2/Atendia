<?php

use App\Enums\NotificationType;
use App\Livewire\Forms\Business\BusinessForm;
use App\Models\Country;
use App\Traits\HasNotifications;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Wizard step 5 — the connection DATA, wired to {@see BusinessForm}: the two
 * WhatsApp numbers (the one the AI answers on, and a human's phone for what
 * it cannot answer) plus the business email, where the welcome lands. No QR
 * and no "connected" claim: the real switch-on lives in the panel, later.
 */
new class extends Component
{
    use HasNotifications;

    public BusinessForm $form;

    /** The DTO must exist before the first render: `setup()` is not a hook. */
    public function mount(): void
    {
        $this->form->setup();
    }

    /**
     * Dial options for the phone control, straight from the catalog. The
     * flag comes from iso2 as regional-indicator pairs — no image to load.
     *
     * @return list<array{code: string, flag: string}>
     */
    #[Computed]
    public function phoneCountries(): array
    {
        return Country::phoneFlags(states: [true]);
    }

    /** The business's own country starts selected: most numbers live there. */
    #[Computed]
    public function defaultDial(): ?string
    {
        return Country::dialCode($this->form->data?->country_id);
    }

    /**
     * The three fields, validated and saved. `connected` stays false on
     * purpose: nothing connects here — that claim returns with the real
     * Cloud API switch-on.
     */
    public function finish(): void
    {
        $notification = $this->form->saveConnection();

        $this->dispatchChangeNotification($notification);

        if ($notification->type === NotificationType::Error) {
            return;
        }

        $this->dispatch('wizard:step-completed', step: 5, skipped: false, connected: false);
    }

    /**
     * "Conectar después" defers the CONNECTION, never the data: whatever is
     * typed saves on the way out — the owner lost her real numbers to the
     * old discard (2026-09-16). `$discard` is her explicit choice when what
     * is typed cannot be saved, and with no business yet there is nowhere
     * to save into, so the exit stays open.
     */
    public function finishLater(bool $discard = false): void
    {
        if (! $discard && $this->form->hasConnectionData() && Auth::user()?->business !== null) {
            $notification = $this->form->saveConnection();

            $this->dispatchChangeNotification($notification);

            if ($notification->type === NotificationType::Error) {
                return;
            }
        }

        $this->dispatch('wizard:step-completed', step: 5, skipped: true, connected: false);
    }
};
?>

<div x-data="stepWhatsappGuard">
    <h2>{{ __('wizard.steps.5.heading') }}</h2>
    <p class="lead">{{ __('wizard.steps.5.lead') }}</p>

    <x-ui.card>
        <div class="wizard-frow">
            <x-inputsform.phone
                span="short"
                required
                name="whatsapp_number"
                alpine-error="whatsapp_number"
                :countries="$this->phoneCountries"
                :default-dial="$this->defaultDial"
                :value="$form->data?->whatsapp_number"
                wire:model="form.data.whatsapp_number"
                :label="__('wizard.fields.whatsapp_number')"
                :placeholder="__('wizard.fields.whatsapp_number_placeholder')"
                :hint="__('wizard.fields.whatsapp_number_hint')"
            />
            <x-inputsform.phone
                span="short"
                required
                name="fallback_whatsapp_number"
                alpine-error="fallback_whatsapp_number"
                :countries="$this->phoneCountries"
                :default-dial="$this->defaultDial"
                :value="$form->data?->fallback_whatsapp_number"
                wire:model="form.data.fallback_whatsapp_number"
                :label="__('wizard.fields.fallback_whatsapp_number')"
                :placeholder="__('wizard.fields.fallback_whatsapp_number_placeholder')"
                :hint="__('wizard.fields.fallback_whatsapp_number_hint')"
            />

            <x-inputsform.input
                span="long"
                type="email"
                required
                name="email"
                alpine-error="email"
                wire:model="form.data.email"
                :label="__('wizard.fields.business_email')"
                :placeholder="__('wizard.fields.business_email_placeholder')"
                :hint="__('wizard.fields.business_email_hint')"
            />
        </div>

        <div class="wizard-foot">
            <x-ui.button variant="ghost" x-on:click="guardLater"> {{ __('wizard.whatsapp.later') }} </x-ui.button>
            <span class="wizard-spacer"></span>
            <x-ui.button variant="primary" x-on:click="guard"> {{ __('wizard.whatsapp.save') }} </x-ui.button>
        </div>
    </x-ui.card>
</div>

@script
    <script>
        // Front mirror of the connection rules: connecting for real demands the
        // three fields — "conectar después" skips this guard entirely.
        Alpine.data('stepWhatsappGuard', () => ({
            errors: {},

            guard() {
                const data = this.$wire.form.data ?? {};

                this.errors = validate(
                    {
                        whatsapp_number: data.whatsapp_number,
                        fallback_whatsapp_number: data.fallback_whatsapp_number,
                        email: data.email,
                    },
                    {
                        whatsapp_number: ['required', 'phone', ['minLength', 6], ['maxLength', 30]],
                        fallback_whatsapp_number: ['required', 'phone', ['minLength', 6], ['maxLength', 30]],
                        email: ['required', 'email', ['maxLength', 255]],
                    },
                );

                if (Object.keys(this.errors).length === 0) {
                    this.$wire.finish();
                }
            },

            // "Conectar después" keeps whatever is typed: complete data saves
            // on the way out; only an incomplete set asks before discarding.
            async guardLater() {
                const data = this.$wire.form.data ?? {};
                const typed = [data.whatsapp_number, data.fallback_whatsapp_number, data.email].some(
                    (value) => String(value ?? '').trim() !== '',
                );

                if (! typed) {
                    this.$wire.finishLater(true);
                    return;
                }

                this.errors = validate(
                    {
                        whatsapp_number: data.whatsapp_number,
                        fallback_whatsapp_number: data.fallback_whatsapp_number,
                        email: data.email,
                    },
                    {
                        whatsapp_number: ['required', 'phone', ['minLength', 6], ['maxLength', 30]],
                        fallback_whatsapp_number: ['required', 'phone', ['minLength', 6], ['maxLength', 30]],
                        email: ['required', 'email', ['maxLength', 255]],
                    },
                );

                if (Object.keys(this.errors).length === 0) {
                    this.$wire.finishLater();
                    return;
                }

                if (
                    await dialog.confirm({
                        title: @js(__('wizard.whatsapp.discard_title')),
                        message: @js(__('wizard.whatsapp.discard_message')),
                        accept: @js(__('wizard.whatsapp.discard_accept')),
                        type: 'warning',
                    })
                ) {
                    this.errors = {};
                    this.$wire.finishLater(true);
                }
            },
        }));
    </script>
@endscript
