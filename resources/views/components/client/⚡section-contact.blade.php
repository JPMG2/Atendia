<?php

use App\Livewire\Forms\Client\ContactForm;
use App\Traits\HasNotifications;
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

    public function save(): void
    {
        $this->dispatchNotification($this->form->save());
    }
};
?>

<x-ui.card class="bp-card" data-section="contacto" x-data="clientContactForm">
    <div class="bp-card-head">
        <h2>{{ __('client.business.contact.title') }}</h2>
    </div>
    <p class="bp-card-sub">{{ __('client.business.contact.sub') }}</p>

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
        <x-ui.button
            variant="primary"
            size="sm"
            x-on:click="submit()"
        >
            {{ __('client.business.actions.save') }}</x-ui.button>
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
