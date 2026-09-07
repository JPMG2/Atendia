<?php

use Livewire\Component;

/**
 * Social networks card of the "Mi negocio" mock-up. Its own section by the
 * owner's call (2026-09-06), never a guest inside Contact: the polymorphic
 * social_links table already backs it for the real wiring.
 */
new class extends Component {};
?>

<x-ui.card class="bp-card" data-section="redes">
    <div class="bp-card-head">
        <h2>{{ __('client.business.social.title') }}</h2>
    </div>
    <p class="bp-card-sub">{{ __('client.business.social.sub') }}</p>

    <div class="bp-form">
        <x-catalog.form-row>
            <x-inputsform.input span="text" icon="instagram" :label="__('client.business.social.instagram')"
                name="instagram" :value="__('client.business.mock.instagram')" />
            <x-inputsform.input span="text" icon="facebook" :label="__('client.business.social.facebook')"
                name="facebook" :placeholder="__('client.business.social.url_placeholder')" />
        </x-catalog.form-row>
    </div>

    <div class="bp-social-add">
        <x-ui.button variant="ghost" size="sm" icon="plus">{{ __('client.business.social.add') }}</x-ui.button>
    </div>

    <div class="bp-card-actions">
        <x-ui.button variant="primary" size="sm">{{ __('client.business.actions.save') }}</x-ui.button>
    </div>
</x-ui.card>
