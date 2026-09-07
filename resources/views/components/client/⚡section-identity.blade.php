<?php

use Livewire\Component;

/**
 * Identity card of the "Mi negocio" mock-up: logo, name and the description
 * the assistant introduces itself with. Saves by itself, like every section.
 */
new class extends Component {};
?>

<x-ui.card class="bp-card" data-section="identidad">
    <div class="bp-card-head">
        <h2>{{ __('client.business.identity.title') }}</h2>
    </div>
    <p class="bp-card-sub">{{ __('client.business.identity.sub') }}</p>

    <div class="bp-form">
        <x-catalog.form-row>
            <x-inputsform.input span="full" :label="__('client.business.identity.name')" name="name" :value="__('client.business.mock.name')" />
        </x-catalog.form-row>
        <x-catalog.form-row>
            <x-inputsform.textarea span="full" :label="__('client.business.identity.description')" name="description"
                :hint="__('client.business.identity.description_hint')" :rows="3">{{ __('client.business.mock.description') }}</x-inputsform.textarea>
        </x-catalog.form-row>
        <x-catalog.form-row>
            <x-inputsform.file span="full" name="logo"
                :label="__('client.business.identity.logo').' · '.__('client.business.optional')"
                :note="__('client.business.identity.logo_hint')" />
        </x-catalog.form-row>
    </div>

    <div class="bp-card-actions">
        <x-ui.button variant="primary" size="sm">{{ __('client.business.actions.save') }}</x-ui.button>
    </div>
</x-ui.card>
