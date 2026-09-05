<?php

use App\Enums\NotificationType;
use App\Livewire\Forms\Business\BusinessForm;
use App\Models\BusinessActivity;
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
new class extends Component
{
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
     * The sector chips, straight from the catalog: the model owns the query,
     * the wizard only asks for the CODE as value — the hinge the DTO speaks.
     *
     * @return array<int, array{value: int|string, label: string}>
     */
    #[Computed]
    public function sectorOptions(): array
    {
        return BusinessSector::options(states: [true], value: fn (BusinessSector $sector): string => $sector->code);
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

    /**
     * The trades of the chosen sector, from the model. Empty until a sector
     * chip is picked, which is what makes the second question appear.
     *
     * @return array<int, array{value: int|string, label: string}>
     */
    #[Computed]
    public function activityOptions(): array
    {
        $sectorId = BusinessSector::idFromCode((string) $this->form->data?->sector);

        return $sectorId === null ? [] : BusinessActivity::options(
            states: [true],
            value: fn (BusinessActivity $activity): string => $activity->code,
            sectorId: $sectorId,
        );
    }

    /** "¿Qué tipo de gastronomía?" — the question names the chosen sector. */
    #[Computed]
    public function activityLabel(): string
    {
        $chosen = collect($this->sectorOptions)->firstWhere('value', $this->form->data?->sector);

        return __('wizard.fields.activity', ['sector' => mb_strtolower((string) ($chosen['label'] ?? ''))]);
    }

    public function choose(string $sector): void
    {
        $this->form->data->sector = $sector;

        // A new sector invalidates the trade: the old one no longer belongs.
        $this->form->data->activity = null;

        unset($this->activityOptions);

        // BEFORE the auto-pick below, or the parent's reset on sector-chosen
        // would wipe the freshly picked trade.
        $this->dispatch('wizard:sector-chosen', sector: $sector);

        // "Otro" has a single trade: asking would be a question with one
        // answer, so it picks itself.
        if (count($this->activityOptions) === 1) {
            $this->chooseActivity((string) $this->activityOptions[0]['value']);

            return;
        }

        // The trade question appears below the fold: a gentle scroll makes
        // sure the person ever sees it. rAF waits for the morph to paint it.
        $this->js('requestAnimationFrame(() => document.querySelector("[data-activity-field]")?.scrollIntoView({ behavior: "smooth", block: "center" }))');
    }

    public function chooseActivity(string $activity): void
    {
        $this->form->data->activity = $activity;

        $this->dispatch('wizard:activity-chosen', activity: $activity);
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

<div x-data="stepBusinessGuard">
    <h2>{{ __('wizard.steps.2.heading') }}</h2>
    <p class="lead">{{ __('wizard.steps.2.lead') }}</p>

    <x-ui.card>
        <x-inputsform.input span="long" required style="text-transform: capitalize;" name="name" alpine-error="name"
            wire:model.live="form.data.name" :label="__('wizard.fields.business_name')" :placeholder="__('wizard.fields.business_name_placeholder')" :hint="__('wizard.fields.business_name_hint')" />

        <div class="wizard-frow">
            <x-inputsform.combobox span="text" required name="country_id" alpine-error="country_id"
                :label="__('wizard.fields.country')" :placeholder="__('wizard.fields.country_placeholder')"
                :options="$this->countryOptions" :value="$form->data?->country_id" wire:model.live="form.data.country_id" />

            <x-inputsform.combobox span="text" required name="province_id" alpine-error="province_id"
                :label="__('wizard.fields.province')" :placeholder="__('wizard.fields.province_placeholder')"
                :hint="__('wizard.fields.province_hint')"
                :options="$this->provinceOptions" :value="$form->data?->province_id"
                loading="form.data.country_id" wire:model.live="form.data.province_id" />
        </div>

        <div class="field">
            <span class="field-label">{{ __('wizard.fields.sector') }}</span>
            <div class="wizard-chips">
                @foreach ($this->sectorOptions as $option)
                    <button type="button" wire:key="sector-{{ $option['value'] }}"
                        wire:click="choose('{{ $option['value'] }}')" @class(['wizard-chip', 'is-on' => $form->data?->sector === $option['value']])>
                        {{ $option['label'] }}
                    </button>
                @endforeach
            </div>
            @error('sector')
                <span class="field-error-text">{{ $message }}</span>
            @enderror
            <span class="field-error-text" x-show="errors.sector" x-text="errors.sector" x-cloak></span>
            <span class="field-hint">{{ __('wizard.fields.sector_hint') }}</span>
        </div>

        @if ($this->activityOptions !== [])
            <div class="field" data-activity-field>
                <span class="field-label">{{ $this->activityLabel }}</span>
                <div class="wizard-chips">
                    @foreach ($this->activityOptions as $option)
                        <button type="button" wire:key="activity-{{ $option['value'] }}"
                            wire:click="chooseActivity('{{ $option['value'] }}')" @class(['wizard-chip', 'is-on' => $form->data?->activity === $option['value']])>
                            {{ $option['label'] }}
                        </button>
                    @endforeach
                </div>
                @error('activity')
                    <span class="field-error-text">{{ $message }}</span>
                @enderror
                <span class="field-error-text" x-show="errors.activity" x-text="errors.activity" x-cloak></span>
                <span class="field-hint">{{ __('wizard.fields.activity_hint') }}</span>
            </div>
        @endif

        <div class="wizard-foot">
            <span class="wizard-spacer"></span>
            <x-ui.button variant="primary" x-on:click="guard">
                {{ __('wizard.continue') }}
            </x-ui.button>
        </div>
    </x-ui.card>
</div>

@script
<script>
    // Front mirror of BusinessForm's identity rules: a doomed request never
    // leaves; the server stays the authority.
    Alpine.data('stepBusinessGuard', () => ({
        errors: {},

        guard() {
            const data = this.$wire.form.data ?? {};

            const rules = {
                name: ['required', ['minLength', 3], ['maxLength', 255], 'noMarkup'],
                country_id: ['required'],
                province_id: ['required'],
                sector: ['required'],
            };

            // The trade question only renders once a sector is picked: its
            // error must never point at something invisible.
            if (data.sector) {
                rules.activity = ['required'];
            }

            this.errors = validate({
                name: data.name,
                country_id: data.country_id,
                province_id: data.province_id,
                sector: data.sector,
                activity: data.activity,
            }, rules);

            if (Object.keys(this.errors).length === 0) {
                this.$wire.finish();
            }
        },
    }));
</script>
@endscript
