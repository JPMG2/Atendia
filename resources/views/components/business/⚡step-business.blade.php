<?php

use App\Enums\NotificationType;
use App\Livewire\Forms\Business\BusinessForm;
use App\Models\BusinessSector;
use App\Models\Country;
use App\Models\Province;
use App\Traits\HasNotifications;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Wizard step 2 — business name, MINIMAL location and sector, wired to
 * {@see BusinessForm}: Continuar validates and CREATES the tenant (or updates
 * it when walking back). The name feeds the phone preview live; the province
 * pins the timezone; the sector drives the suggestions of step 3.
 */
new class extends Component {
    use HasNotifications;

    public BusinessForm $form;

    /** The DTO must exist before the first render: `setup()` is not a hook. */
    public function mount(): void
    {
        $this->form->setup();
    }

    /** @return array<int, array{value: int, label: string}> */
    #[Computed]
    public function countryOptions(): array
    {
        return Country::options(states: [true], label: fn (Country $country): string => $country->name);
    }

    /**
     * Provinces of the chosen country. The computed READS the form's country,
     * so the render after picking one already carries the right list.
     *
     * @return array<int, array{value: int, label: string}>
     */
    #[Computed]
    public function provinceOptions(): array
    {
        $countryId = $this->form->data?->country_id;

        return $countryId ? Province::options(states: [true], label: fn (Province $province): string => $province->name, countryId: $countryId) : [];
    }

    /**
     * The sector chips, straight from the catalog: the seeder carries the
     * demand-ordered research, so the wizard never hardcodes the list.
     *
     * @return array<int, array{code: string, name: string}>
     */
    #[Computed]
    public function sectorOptions(): array
    {
        return BusinessSector::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['code', 'name'])
            ->map(fn (BusinessSector $sector): array => ['code' => $sector->code, 'name' => $sector->name])
            ->all();
    }

    /**
     * The country cascade lives on the form ({@see BusinessForm}); this
     * catch-all only forwards the live name to the phone preview.
     */
    public function updated(string $property): void
    {
        if ($property === 'form.data.name') {
            $this->dispatch('wizard:name-updated', name: $this->form->data->name ?? '');
        }
    }

    public function choose(string $sector): void
    {
        $this->form->data->sector = $sector;

        $this->dispatch('wizard:sector-chosen', sector: $sector);
    }

    /** Advances ONLY on a real save: a validation error keeps the step open. */
    public function finish(): void
    {
        $notification = $this->form->saveIdentity();

        $this->dispatchNotification($notification);

        if ($notification->type === NotificationType::Error) {
            return;
        }

        $this->dispatch('wizard:step-completed', step: 2);
    }
};
?>

<div>
    <h2>{{ __('wizard.steps.2.heading') }}</h2>
    <p class="lead">{{ __('wizard.steps.2.lead') }}</p>

    <x-ui.card>
        <x-inputsform.input span="long" required style="text-transform: capitalize;" name="name"
            wire:model.live="form.data.name" :label="__('wizard.fields.business_name')" :placeholder="__('wizard.fields.business_name_placeholder')" :hint="__('wizard.fields.business_name_hint')" />

        <div class="wizard-frow">
            <x-inputsform.combobox span="text" required name="country_id"
                :label="__('wizard.fields.country')" :placeholder="__('wizard.fields.country_placeholder')"
                :options="$this->countryOptions" :value="$form->data?->country_id" wire:model.live="form.data.country_id" />

            <x-inputsform.combobox span="text" required name="province_id"
                :label="__('wizard.fields.province')" :placeholder="__('wizard.fields.province_placeholder')"
                :hint="__('wizard.fields.province_hint')"
                :options="$this->provinceOptions" :value="$form->data?->province_id"
                loading="form.data.country_id" wire:model.live="form.data.province_id" />
        </div>

        <div class="field">
            <span class="field-label">{{ __('wizard.fields.sector') }}</span>
            <div class="wizard-chips">
                @foreach ($this->sectorOptions as $option)
                    <button type="button" wire:key="sector-{{ $option['code'] }}"
                        wire:click="choose('{{ $option['code'] }}')" @class(['wizard-chip', 'is-on' => $form->data?->sector === $option['code']])>
                        {{ $option['name'] }}
                    </button>
                @endforeach
            </div>
            @error('sector')
                <span class="field-error-text">{{ $message }}</span>
            @enderror
            <span class="field-hint">{{ __('wizard.fields.sector_hint') }}</span>
        </div>

        <div class="wizard-foot">
            <span class="wizard-spacer"></span>
            <x-ui.button variant="primary" wire:click="finish">
                {{ __('wizard.continue') }}
            </x-ui.button>
        </div>
    </x-ui.card>
</div>
