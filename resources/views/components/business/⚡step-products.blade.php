<?php

use App\Enums\NotificationType;
use App\Livewire\Forms\Business\BusinessForm;
use App\Models\BusinessActivity;
use App\Models\BusinessSector;
use App\Models\ProductImport;
use App\Traits\HasNotifications;
use App\Traits\ImportsProducts;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Reactive;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Wizard step 4 — the products. The spreadsheet path lives in the shared
 * {@see ImportsProducts} flow (one confirmation, the queued job writes);
 * the manual list persists through {@see BusinessForm::saveProducts()}.
 * Skipping writes nothing.
 */
new class extends Component
{
    use HasNotifications;
    use ImportsProducts;
    use WithFileUploads;

    public BusinessForm $form;

    #[Reactive]
    public string $sector = '';

    #[Reactive]
    public string $activity = '';

    /** @var list<string> */
    public array $products = [];

    /**
     * What the screen actually showed: the only names Continuar may drop.
     * The import job writes behind this step, and reconciling against the
     * pills alone wiped its rows once. Locked: the front never edits it.
     *
     * @var list<string>
     */
    #[Locked]
    public array $knownProducts = [];

    public string $draft = '';

    /** Re-entry shows what a previous pass saved; the preview hears it too. */
    public function mount(): void
    {
        $this->products = Auth::user()?->business?->productNames() ?? [];
        $this->knownProducts = $this->products;

        if ($this->products !== []) {
            $this->dispatch('wizard:products-updated', products: $this->products);
        }
    }

    /** The upload's rules live on the Form, never inline in the component. */
    protected function validateImportUpload(mixed $file): void
    {
        $this->form->validateImportUpload($file);
    }

    /**
     * With a real row in hand the preview asks for it, whatever the trade —
     * no canned demo product for a doctor to sell car parts.
     */
    protected function importQueued(ProductImport $import): void
    {
        $this->dispatch('wizard:products-imported');

        if ($this->sampleProduct !== null) {
            $this->dispatch('wizard:products-updated', products: array_values(array_unique([...$this->products, $this->sampleProduct])));
        }
    }

    public function add(?string $name = null): void
    {
        $name = trim($name ?? $this->draft);

        $this->draft = '';

        if ($name === '' || in_array($name, $this->products, true)) {
            return;
        }

        $this->products[] = $name;

        $this->dispatch('wizard:products-updated', products: $this->products);
    }

    /**
     * The trade's suggested names that are goods: the services step leaves
     * them out, so a pizza is offered once and saved where /productos reads.
     *
     * @return list<string>
     */
    #[Computed]
    public function suggestions(): array
    {
        if ($this->activity !== '') {
            return BusinessActivity::suggestionsName(code: $this->activity, goods: true);
        }

        return $this->sector === '' ? [] : BusinessSector::suggestionsName(code: $this->sector, goods: true);
    }

    /** Chips behave like checkboxes: a second tap on a picked one un-picks it. */
    public function toggle(string $name): void
    {
        $index = array_search($name, $this->products, true);

        if ($index === false) {
            $this->add($name);

            return;
        }

        $this->remove($index);
    }

    public function remove(int $index): void
    {
        unset($this->products[$index]);

        $this->products = array_values($this->products);

        $this->dispatch('wizard:products-updated', products: $this->products);
    }

    /** Advances ONLY on a real save; a skip is the promise of writing nothing. */
    public function finish(bool $skipped = false): void
    {
        if (! $skipped) {
            // The box has no add button: a name typed and never confirmed with Enter would vanish.
            $this->add();

            $notification = $this->form->saveProducts($this->products, $this->knownProducts);

            $this->dispatchChangeNotification($notification);

            if ($notification->type === NotificationType::Error) {
                return;
            }

            // The list just saved is now the screen's truth for a re-run.
            $this->knownProducts = $this->products;
        }

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
        @if ($headers === [])
            <x-inputsform.file
                span="full"
                name="upload"
                wire:model="upload"
                accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv"
                :note="__('wizard.products.drop_formats')"
            >
                <b class="block">{{ __('wizard.products.drop_title') }}</b>
                {{ __('wizard.products.drop_text') }}
            </x-inputsform.file>
        @else
            <x-client.import-review
                :headers="$headers"
                :labels="$labels"
                :mapping="$mapping"
                :fix-originals="$fixOriginals"
                :fixes="$fixes"
                :total-rows="$totalRows"
                :target-options="$this->targetOptions"
            />
        @endif

        @if ($queuedFile !== null)
            <p class="wizard-import-ok">
                {{ __('wizard.products.queued', ['file' => $queuedFile, 'rows' => $totalRows]) }}
            </p>
        @endif

        <p class="wizard-suggest">{{ __('wizard.products.manual') }}</p>

        <x-inputsform.input
            span="long"
            name="product_draft"
            maxlength="255"
            wire:model="draft"
            wire:keydown.enter.prevent="add"
            :label="__('wizard.fields.product')"
            :placeholder="__('wizard.fields.product_placeholder')"
        />

        <div class="wizard-add">
            <x-ui.button variant="secondary" size="sm" icon="plus" wire:click="add">{{ __('wizard.products.add') }}</x-ui.button>
        </div>

        @if ($this->suggestions !== [])
            <p class="wizard-suggest">{{ __('wizard.products.suggest') }}</p>
            <div class="wizard-chips">
                @foreach ($this->suggestions as $suggestion)
                    @php
                        // Same exact comparison add() dedupes with.
                        $picked = in_array($suggestion, $products, true);
                    @endphp

                    <button
                        type="button"
                        wire:key="suggest-{{ $suggestion }}"
                        wire:click="toggle(@js($suggestion))"
                        @class(['wizard-pill-suggest', 'is-on' => $picked])
                        aria-pressed="{{ $picked ? 'true' : 'false' }}"
                    >
                        @if ($picked)
                            <x-icon name="check" :size="12" />
                        @else
                            +
                        @endif
                        {{ $suggestion }}
                    </button>
                @endforeach
            </div>
        @endif

        <div class="wizard-pills">
            @foreach ($products as $index => $product)
                <span wire:key="product-{{ md5($product) }}" class="wizard-pill">
                    {{ $product }}
                    <button
                        type="button"
                        wire:click="remove({{ $index }})"
                        aria-label="{{ __('wizard.services.remove') }}"
                    >
                        ×
                    </button>
                </span>
            @endforeach
        </div>

        <div class="wizard-foot">
            <x-ui.button variant="ghost" wire:click="finish(true)"> {{ __('wizard.products.skip') }} </x-ui.button>
            <span class="wizard-spacer"></span>
            <x-ui.button variant="primary" wire:click="finish"> {{ __('wizard.continue') }} </x-ui.button>
        </div>
    </x-ui.card>
</div>
