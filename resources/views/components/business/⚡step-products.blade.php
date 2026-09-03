<?php

use Livewire\Component;

/**
 * Wizard step 4 — the inventory import, simulated. Clicking the drop zone
 * plays the happy path so the phone preview can answer a stock question;
 * the real upload arrives with the import feature.
 */
new class extends Component {
    public bool $imported = false;

    public function simulateImport(): void
    {
        $this->imported = true;

        $this->dispatch('wizard:products-imported');
    }

    public function finish(bool $skipped = false): void
    {
        $this->dispatch('wizard:step-completed', step: 4, skipped: $skipped);
    }
};
?>

<div>
    <h2>
        {{ __('wizard.steps.4.heading') }}
        <span class="wizard-optional">{{ __('wizard.optional') }}</span>
    </h2>
    <p class="lead">{{ __('wizard.steps.4.lead') }}</p>

    <x-ui.card>
        <button type="button" class="wizard-drop" wire:click="simulateImport">
            <b>{{ __('wizard.products.drop_title') }}</b>
            {{ __('wizard.products.drop_text') }}
            <span class="fmt">{{ __('wizard.products.drop_formats') }}</span>
        </button>

        @if ($imported)
            <p class="wizard-import-ok">{{ __('wizard.products.import_ok') }}</p>
        @endif

        <div class="wizard-foot">
            <x-ui.button variant="ghost" wire:click="finish(true)">
                {{ __('wizard.products.skip') }}
            </x-ui.button>
            <span class="wizard-spacer"></span>
            <x-ui.button variant="primary" wire:click="finish">
                {{ __('wizard.continue') }}
            </x-ui.button>
        </div>
    </x-ui.card>
</div>
