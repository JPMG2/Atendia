<?php

use App\Livewire\Forms\Client\ContactForm;
use App\Models\Country;
use App\Traits\HasNotifications;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Contact card of "Mi negocio". The state, validation and save live in
 * {@see ContactForm}; the component only wires the screen to it.
 */
new class extends Component
{
    use HasNotifications;

    public ContactForm $form;

    public function mount(): void
    {
        $this->form->setup();
    }

    /**
     * Dial options for the phone controls, straight from the catalog.
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
        return Country::dialCode(Auth::user()?->business?->country_id);
    }

    public function save(): void
    {
        $this->dispatchNotification($this->form->save());
    }
};
?>

<x-ui.card class="bp-card" data-section="contacto" x-data="clientContactForm">
    <div
        x-data="sectionDirty"
        x-on:input="markDirty()"
        x-on:change="markDirty()"
        x-on:click="trackSave($event)"
        x-on:notify.window="settle($event.detail)"
    >
    <div class="bp-card-head">
        <h2>{{ __('client.business.contact.title') }}</h2>
            <span class="status-tag is-warning" x-show="dirty" x-cloak>{{ __('client.business.unsaved_pill') }}</span>
    </div>
    <p class="bp-card-sub">{{ __('client.business.contact.sub') }}</p>

    {{-- The two WhatsApp numbers lived only in the wizard until the owner
    skipped that step and had no door left (2026-09-16). --}}
    <x-catalog.form-row>
        <x-inputsform.phone
            span="text"
            name="whatsapp_number"
            alpine-error="whatsapp_number"
            :countries="$this->phoneCountries"
            :default-dial="$this->defaultDial"
            :value="$form->whatsapp_number"
            wire:model="form.whatsapp_number"
            :label="__('wizard.fields.whatsapp_number').' · '.__('client.business.optional')"
            :hint="__('wizard.fields.whatsapp_number_hint')"
        />
        <x-inputsform.phone
            span="text"
            name="fallback_whatsapp_number"
            alpine-error="fallback_whatsapp_number"
            :countries="$this->phoneCountries"
            :default-dial="$this->defaultDial"
            :value="$form->fallback_whatsapp_number"
            wire:model="form.fallback_whatsapp_number"
            :label="__('wizard.fields.fallback_whatsapp_number').' · '.__('client.business.optional')"
            :hint="__('wizard.fields.fallback_whatsapp_number_hint')"
        />
    </x-catalog.form-row>

    <x-catalog.form-row>
        <x-inputsform.input
            span="text"
            :label="__('client.business.contact.email').' · '.__('client.business.optional')"
            name="email"
            alpine-error="email"
            wire:model="form.email"
        />
        <x-inputsform.input
            span="text"
            :label="__('client.business.contact.web').' · '.__('client.business.optional')"
            name="web"
            alpine-error="web"
            :placeholder="__('client.business.contact.web_placeholder')"
            wire:model="form.web"
        />
    </x-catalog.form-row>

    <div class="bp-card-actions">
        <x-ui.button x-bind:class="{ 'is-idle': ! dirty }"
            variant="primary"
            size="sm"
            x-on:click="submit()"
        >
            {{ __('client.business.actions.save') }}</x-ui.button>
    </div>
    </div>
</x-ui.card>

@script
    <script>
        /*
         * FRONT validation of the contact card, same criterion as the company
         * screen: the rules mirror the server's replicable half. The URL
         * format is Laravel's `url:` and still bounces from the server.
         */
        Alpine.data('clientContactForm', () => ({
            errors: {},

            // Where the form lives on the server: values are read from there,
            // since wire:model is the card's only state.
            path: 'form',

            rules: {
                whatsapp_number: ['phone', ['maxLength', 30]],
                fallback_whatsapp_number: ['phone', ['maxLength', 30]],
                email: ['email', ['maxLength', 255]],
                web: [['maxLength', 255], 'noMarkup'],
            },

            async submit() {
                const values = {};

                for (const field in this.rules) {
                    values[field] = this.$wire.get(`${this.path}.${field}`);
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
