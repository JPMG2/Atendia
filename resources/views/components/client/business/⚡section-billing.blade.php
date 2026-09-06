<?php

use Livewire\Component;

/**
 * Currency and billing card of the "Mi negocio" mock-up. Everything optional:
 * no tax data means the invoice goes out to a natural person, and the
 * reference currency mirrors the Venezuelan "Ref" habit.
 */
new class extends Component {};
?>

<x-ui.card class="bp-card" data-section="facturacion">
    <div class="bp-card-head">
        <h2>{{ __('client.business.billing.title') }}</h2>
    </div>
    <p class="bp-card-sub">{{ __('client.business.billing.sub') }}</p>

    <div class="bp-form">
        <x-catalog.form-row>
            <x-inputsform.select span="text" :label="__('client.business.billing.currency')" name="currency_id"
                :options="[__('client.business.mock.currency')]" />
            <x-inputsform.select span="text" :label="__('client.business.billing.reference').' · '.__('client.business.optional')"
                name="reference_currency_id" :options="[__('client.business.mock.reference')]"
                :hint="__('client.business.billing.reference_hint')" />
        </x-catalog.form-row>
        <x-catalog.form-row>
            <x-inputsform.select span="text" :label="__('client.business.billing.tax_condition').' · '.__('client.business.optional')"
                name="tax_condition_id" :options="[]" :placeholder="__('client.business.billing.tax_condition_placeholder')" />
            <x-inputsform.input span="text" :label="__('client.business.billing.tax_id').' · '.__('client.business.optional')"
                name="tax_id" :placeholder="__('client.business.billing.tax_id_placeholder')" class="font-mono" />
        </x-catalog.form-row>
    </div>
    <p class="bp-hint">{{ __('client.business.billing.natural_hint') }}</p>

    <div class="bp-card-actions">
        <x-ui.button variant="primary" size="sm">{{ __('client.business.actions.save') }}</x-ui.button>
    </div>
</x-ui.card>
