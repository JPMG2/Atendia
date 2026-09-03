<?php

use App\Models\ServiceType;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Reactive;
use Livewire\Component;

/**
 * Wizard step 3 — the services, one Enter at a time. Suggestions follow the
 * sector chosen on step 2 (reactive: it may change after this step mounted).
 * Every change travels up so the phone preview answers with the real list.
 */
new class extends Component {
    #[Reactive]
    public string $sector = '';

    /** @var list<string> */
    public array $services = [];

    public string $draft = '';

    /**
     * The top service types of the chosen sector, via its ACTIVITIES: on
     * `ServiceType` the sector is only admin grouping, the activity pivot is
     * who actually offers what — and it stays a suggestion, never a fence.
     *
     * @return list<string>
     */
    #[Computed]
    public function suggestions(): array
    {
        if ($this->sector === '') {
            return [];
        }

        return ServiceType::query()
            ->where('is_active', true)
            ->whereHas('activities.sector', fn ($query) => $query->where('code', $this->sector))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    public function add(?string $name = null): void
    {
        $name = trim($name ?? $this->draft);

        $this->draft = '';

        if ($name === '' || in_array($name, $this->services, true)) {
            return;
        }

        $this->services[] = $name;

        $this->dispatch('wizard:services-updated', services: $this->services);
    }

    public function remove(int $index): void
    {
        unset($this->services[$index]);

        $this->services = array_values($this->services);

        $this->dispatch('wizard:services-updated', services: $this->services);
    }

    public function finish(bool $skipped = false): void
    {
        $this->dispatch('wizard:step-completed', step: 3, skipped: $skipped);
    }
};
?>

<div>
    <h2>{{ __('wizard.steps.3.heading') }}</h2>
    <p class="lead">{{ __('wizard.steps.3.lead') }}</p>

    <x-ui.card>
        <x-inputsform.input span="long" name="service_draft" wire:model="draft" wire:keydown.enter.prevent="add"
            :label="__('wizard.fields.service')"
            :placeholder="__('wizard.fields.service_placeholder')" />

        @if ($this->suggestions !== [])
            <p class="wizard-suggest">{{ __('wizard.services.suggest') }}</p>
            <div class="wizard-chips">
                @foreach ($this->suggestions as $suggestion)
                    <button type="button" wire:key="suggest-{{ $suggestion }}"
                            wire:click="add('{{ $suggestion }}')" class="wizard-pill-suggest">
                        + {{ $suggestion }}
                    </button>
                @endforeach
            </div>
        @endif

        <div class="wizard-pills">
            @foreach ($services as $index => $service)
                <span wire:key="service-{{ md5($service) }}" class="wizard-pill">
                    {{ $service }}
                    <button type="button" wire:click="remove({{ $index }})"
                            aria-label="{{ __('wizard.services.remove') }}">×</button>
                </span>
            @endforeach
        </div>

        <div class="wizard-foot">
            <x-ui.button variant="ghost" wire:click="finish(true)">
                {{ __('wizard.services.skip') }}
            </x-ui.button>
            <span class="wizard-spacer"></span>
            <x-ui.button variant="primary" wire:click="finish">
                {{ __('wizard.continue') }}
            </x-ui.button>
        </div>
    </x-ui.card>
</div>
