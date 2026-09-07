<?php

use Livewire\Component;

/**
 * Contact card of the "Mi negocio" mock-up: the public channels besides
 * WhatsApp, both optional.
 */
new class extends Component {};
?>

<x-ui.card class="bp-card" data-section="contacto">
    <div class="bp-card-head">
        <h2>{{ __('client.business.contact.title') }}</h2>
    </div>
    <p class="bp-card-sub">{{ __('client.business.contact.sub') }}</p>

    <x-catalog.form-row>
        <x-inputsform.input span="text" :label="__('client.business.contact.email').' · '.__('client.business.optional')"
            name="email" :value="__('client.business.mock.email')" />
        <x-inputsform.input span="text" :label="__('client.business.contact.web').' · '.__('client.business.optional')"
            name="web" :placeholder="__('client.business.contact.web_placeholder')" />
    </x-catalog.form-row>

    <div class="bp-card-actions">
        <x-ui.button variant="primary" size="sm">{{ __('client.business.actions.save') }}</x-ui.button>
    </div>
</x-ui.card>
