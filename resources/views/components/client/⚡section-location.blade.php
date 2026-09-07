<?php

use Livewire\Component;

/**
 * Location card of the "Mi negocio" mock-up. The GBP-style premises question
 * folds the address away when the answer is no — the only interactive bit.
 */
new class extends Component
{
    public bool $hasPremises = true;
};
?>

<x-ui.card class="bp-card" data-section="ubicacion">
    <div class="bp-card-head">
        <h2>{{ __('client.business.location.title') }}</h2>
    </div>
    <p class="bp-card-sub">{{ __('client.business.location.sub') }}</p>

    <div class="bp-question">
        <span>{{ __('client.business.location.question') }}</span>
        <div class="mock-switch" role="group" aria-label="{{ __('client.business.location.question') }}">
            <button type="button" wire:click="$set('hasPremises', true)" @class(['is-active' => $hasPremises])>
                {{ __('client.business.location.yes') }}
            </button>
            <button type="button" wire:click="$set('hasPremises', false)" @class(['is-active' => ! $hasPremises])>
                {{ __('client.business.location.no') }}
            </button>
        </div>
    </div>

    @if ($hasPremises)
        <div class="bp-form">
            <x-catalog.form-row>
                <x-inputsform.input span="long" :label="__('client.business.location.address')" name="address"
                    :placeholder="__('client.business.location.address_placeholder')" />
                <x-inputsform.input span="short" :label="__('client.business.location.city')" name="city"
                    :placeholder="__('client.business.location.city_placeholder')" />
            </x-catalog.form-row>
            <x-catalog.form-row>
                <x-inputsform.input span="short" :label="__('client.business.location.country')" name="country"
                    :value="__('client.business.mock.country')" disabled />
                <x-inputsform.input span="short" :label="__('client.business.location.province')" name="province"
                    :value="__('client.business.mock.province')" disabled />
            </x-catalog.form-row>
        </div>
    @endif

    <div class="bp-card-actions">
        <x-ui.button variant="primary" size="sm">{{ __('client.business.actions.save') }}</x-ui.button>
    </div>
</x-ui.card>
