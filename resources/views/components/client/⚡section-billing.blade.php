<?php

use App\Livewire\Forms\Client\BillingForm;
use App\Models\Currency;
use App\Models\TaxCondition;
use App\Traits\HasNotifications;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Currency and billing card of "Mi negocio". The state, validation and save
 * live in {@see BillingForm}; the component only wires the screen to it and
 * feeds the comboboxes.
 */
new class extends Component
{
    use HasNotifications;

    public BillingForm $form;

    public function mount(): void
    {
        $this->form->setup();
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    #[Computed]
    public function currencyOptions(): array
    {
        return Currency::options([true]);
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    #[Computed]
    public function taxConditionOptions(): array
    {
        return TaxCondition::options([true], countryId: Auth::user()?->business?->country_id);
    }

    public function save(): void
    {
        $this->dispatchNotification($this->form->save());
    }
};
?>

<x-ui.card class="bp-card" data-section="facturacion" x-data="clientBillingForm">
    <div class="bp-card-head">
        <h2>{{ __('client.business.billing.title') }}</h2>
    </div>
    <p class="bp-card-sub">{{ __('client.business.billing.sub') }}</p>

    <div class="bp-form">
        <x-catalog.form-row>
            <x-inputsform.combobox
                span="text"
                :label="__('client.business.billing.currency')"
                name="currency_id"
                :options="$this->currencyOptions"
                :value="$form->currency_id"
                wire:model="form.currency_id"
            />
            <x-inputsform.combobox
                span="text"
                :label="__('client.business.billing.reference').' · '.__('client.business.optional')"
                name="reference_currency_id"
                :options="$this->currencyOptions"
                :value="$form->reference_currency_id"
                wire:model="form.reference_currency_id"
                :hint="__('client.business.billing.reference_hint')"
            />
        </x-catalog.form-row>
        <x-catalog.form-row>
            <x-inputsform.combobox
                span="text"
                :label="__('client.business.billing.tax_condition').' · '.__('client.business.optional')"
                name="tax_condition_id"
                :options="$this->taxConditionOptions"
                :value="$form->tax_condition_id"
                wire:model="form.tax_condition_id"
                :placeholder="__('client.business.billing.tax_condition_placeholder')"
            />
            <x-inputsform.input
                span="text"
                :label="__('client.business.billing.tax_id').' · '.__('client.business.optional')"
                name="tax_id"
                alpine-error="tax_id"
                wire:model="form.tax_id"
                :placeholder="__('client.business.billing.tax_id_placeholder')"
                class="font-mono"
            />
        </x-catalog.form-row>
    </div>
    <p class="bp-hint">{{ __('client.business.billing.natural_hint') }}</p>

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
         * FRONT validation of the billing card, same criterion as the company
         * screen: the rules mirror the server's replicable half. The three
         * comboboxes are `exists` checks, so they still bounce from the server.
         */
        Alpine.data('clientBillingForm', () => ({
            errors: {},

            // Where the form lives on the server: values are read from there,
            // since wire:model is the card's only state.
            path: 'form',

            rules: {
                tax_id: [['maxLength', 20]],
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
