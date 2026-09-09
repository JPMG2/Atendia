<?php

use App\Livewire\Forms\Client\LocationForm;
use App\Traits\HasNotifications;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Location card of "Mi negocio". The state, validation and save live in
 * {@see LocationForm}; the component only wires the screen to it. Country
 * and province come from the registration and are read-only here.
 */
new class extends Component
{
    use HasNotifications;

    public LocationForm $form;

    public function mount(): void
    {
        $this->form->setup();
    }

    #[Computed]
    public function countryName(): ?string
    {
        return Auth::user()->business?->country?->name;
    }

    #[Computed]
    public function provinceName(): ?string
    {
        return Auth::user()->business?->province?->name;
    }

    public function save(): void
    {
        $this->dispatchNotification($this->form->save());
    }
};
?>

<x-ui.card class="bp-card" data-section="ubicacion" x-data="clientLocationForm">
    <div class="bp-card-head">
        <h2>{{ __('client.business.location.title') }}</h2>
    </div>
    <p class="bp-card-sub">{{ __('client.business.location.sub') }}</p>

    <div class="bp-question">
        <span>{{ __('client.business.location.question') }}</span>
        <div class="mock-switch" role="group" aria-label="{{ __('client.business.location.question') }}">
            <button type="button" wire:click="$set('form.hasPremises', true)" @class(['is-active' => $form->hasPremises === true])>
                {{ __('client.business.location.yes') }}
            </button>
            <button type="button" wire:click="$set('form.hasPremises', false)" @class(['is-active' => $form->hasPremises === false])>
                {{ __('client.business.location.no') }}
            </button>
        </div>
    </div>

    @if ($form->hasPremises)
        <div class="bp-form">
            <x-catalog.form-row>
                <x-inputsform.input span="long" :label="__('client.business.location.address')" name="address"
                    alpine-error="address" :placeholder="__('client.business.location.address_placeholder')" wire:model="form.address" />
                <x-inputsform.input span="short" :label="__('client.business.location.city')" name="city"
                    alpine-error="city" :placeholder="__('client.business.location.city_placeholder')" wire:model="form.city" />
            </x-catalog.form-row>
            <x-catalog.form-row>
                <x-inputsform.input span="short" :label="__('client.business.location.country')" name="country"
                    :value="$this->countryName" disabled />
                <x-inputsform.input span="short" :label="__('client.business.location.province')" name="province"
                    :value="$this->provinceName" disabled />
            </x-catalog.form-row>
        </div>
    @endif

    <div class="bp-card-actions">
        <x-ui.button variant="primary" size="sm" x-on:click="submit()">{{ __('client.business.actions.save') }}</x-ui.button>
    </div>
</x-ui.card>

@script
    <script>
        /*
         * FRONT validation of the location card, same criterion as the company
         * screen: the rules mirror the server's replicable half. Both fields
         * are optional, so the checks only run on what was typed.
         */
        Alpine.data('clientLocationForm', () => ({
            errors: {},

            // Where the form lives on the server: values are read from there,
            // since wire:model is the card's only state.
            path: 'form',

            rules: {
                address: [['minLength', 3], ['maxLength', 255]],
                city: [['minLength', 3], ['maxLength', 255]],
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
