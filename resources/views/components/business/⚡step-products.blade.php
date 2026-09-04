<?php

use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Jobs\ProcessProductImport;
use App\Livewire\Forms\Business\BusinessForm;
use App\Services\ProductImport\ColumnMapper;
use App\Services\ProductImport\ImportFileReader;
use App\Traits\HasNotifications;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Wizard step 4 — the products. The spreadsheet path reads the file, lets the
 * mapper propose where each column lands and asks for ONE confirmation; the
 * queued job does the heavy write. The manual list persists through
 * {@see BusinessForm::saveProducts()}. Skipping writes nothing.
 */
new class extends Component {
    use HasNotifications;
    use WithFileUploads;

    public BusinessForm $form;

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $upload = null;

    /** @var list<string> */
    public array $headers = [];

    /** @var list<string> One proposed target per column, index-aligned. */
    public array $mapping = [];

    /** @var list<string> The headers with typos fixed — editable suggestions. */
    public array $labels = [];

    public int $totalRows = 0;

    /** The first product of the sheet: the preview asks for something REAL. */
    public ?string $sampleProduct = null;

    public ?string $queuedFile = null;

    /** @var list<string> */
    public array $products = [];

    public string $draft = '';

    /** Re-entry shows what a previous pass saved; the preview hears it too. */
    public function mount(): void
    {
        $this->products = Auth::user()?->business?->products()->orderBy('id')->pluck('name')->all() ?? [];

        if ($this->products !== []) {
            $this->dispatch('wizard:products-updated', products: $this->products);
        }
    }

    /** The label of each mapping destination, for the review selects. */
    #[Computed]
    public function targetOptions(): array
    {
        return __('wizard.products.targets');
    }

    /** A fresh file opens the review: read the shape, propose the mapping. */
    public function updatedUpload(): void
    {
        $this->validate(
            ['upload' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:10240']],
            [],
            ['upload' => __('wizard.fields.import_file')],
        );

        try {
            $summary = app(ImportFileReader::class)->read($this->upload->getRealPath());
        } catch (\Throwable) {
            $summary = ['headers' => [], 'samples' => [], 'total_rows' => 0];
        }

        if ($summary['headers'] === []) {
            $this->reset('upload');

            $this->dispatchNotification(new NotificationDto(__('wizard.products.unreadable'), NotificationType::Error));

            return;
        }

        $proposal = app(ColumnMapper::class)->map($summary['headers'], $summary['samples']);

        $nameIndex = array_search('name', $proposal['targets'], true);

        $this->headers = $summary['headers'];
        $this->totalRows = $summary['total_rows'];
        $this->mapping = $proposal['targets'];
        $this->labels = $proposal['labels'];
        $sample = $nameIndex === false ? '' : trim($summary['samples'][0][$nameIndex] ?? '');
        $this->sampleProduct = $sample === '' ? null : $sample;
        $this->queuedFile = null;
    }

    /** Stores the file and the confirmed mapping; the queued job takes over. */
    public function confirmImport(): void
    {
        $business = Auth::user()?->business;

        if ($business === null || $this->upload === null) {
            $this->dispatchNotification(new NotificationDto(__('notifications.not_found'), NotificationType::Error));

            return;
        }

        $original = $this->upload->getClientOriginalName();

        $path = $this->upload->storeAs(
            'imports/business-'.$business->id,
            now()->format('YmdHis').'-'.Str::slug(pathinfo($original, PATHINFO_FILENAME)).'.'.strtolower($this->upload->getClientOriginalExtension()),
            'local',
        );

        $import = $business->productImports()->create([
            'original_name' => $original,
            'path' => $path,
            'mapping' => collect($this->headers)
                ->map(fn (string $header, int $index): array => [
                    'column' => $header,
                    'label' => trim($this->labels[$index] ?? '') !== '' ? trim($this->labels[$index]) : $header,
                    'target' => $this->mapping[$index] ?? 'extra',
                ])
                ->values()
                ->all(),
            'total_rows' => $this->totalRows,
            'status' => 'pending',
        ]);

        ProcessProductImport::dispatch($import->id);

        $this->queuedFile = $original;

        $this->reset('upload', 'headers', 'mapping', 'labels');

        $this->dispatch('wizard:products-imported');

        // With a real row in hand the preview asks for it, whatever the
        // trade — no canned demo product for a doctor to sell car parts.
        if ($this->sampleProduct !== null) {
            $this->dispatch('wizard:products-updated', products: array_values(array_unique([...$this->products, $this->sampleProduct])));
        }
    }

    public function cancelUpload(): void
    {
        $this->reset('upload', 'headers', 'mapping', 'labels');

        $this->totalRows = 0;
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
            $notification = $this->form->saveProducts($this->products);

            $this->dispatchNotification($notification);

            if ($notification->type === NotificationType::Error) {
                return;
            }
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
            <x-inputsform.file span="full" name="upload" wire:model="upload"
                accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv"
                :note="__('wizard.products.drop_formats')">
                <b class="block">{{ __('wizard.products.drop_title') }}</b>
                {{ __('wizard.products.drop_text') }}
            </x-inputsform.file>
        @else
            <div class="wizard-map">
                <h3>{{ __('wizard.products.review_title') }}</h3>
                <p>{{ __('wizard.products.review_hint', ['rows' => $totalRows]) }}</p>

                @foreach ($headers as $index => $header)
                    <div class="wizard-map-row" wire:key="map-{{ $index }}">
                        <div class="wizard-map-colbox">
                            <x-inputsform.input span="full" name="label_{{ $index }}"
                                wire:model="labels.{{ $index }}" />
                            @if (($labels[$index] ?? $header) !== $header)
                                <span class="wizard-map-was">{{ __('wizard.products.was', ['column' => $header]) }}</span>
                            @endif
                        </div>
                        <x-ui.select name="map_{{ $index }}" :options="$this->targetOptions"
                            wire:model="mapping.{{ $index }}" />
                    </div>
                @endforeach

                <div class="wizard-foot">
                    <x-ui.button variant="ghost" wire:click="cancelUpload">
                        {{ __('wizard.products.cancel') }}
                    </x-ui.button>
                    <span class="wizard-spacer"></span>
                    <x-ui.button variant="primary" wire:click="confirmImport">
                        {{ __('wizard.products.confirm') }}
                    </x-ui.button>
                </div>
            </div>
        @endif

        @if ($queuedFile !== null)
            <p class="wizard-import-ok">{{ __('wizard.products.queued', ['file' => $queuedFile, 'rows' => $totalRows]) }}</p>
        @endif

        <p class="wizard-suggest">{{ __('wizard.products.manual') }}</p>

        <x-inputsform.input span="long" name="product_draft" wire:model="draft" wire:keydown.enter.prevent="add"
            :label="__('wizard.fields.product')"
            :placeholder="__('wizard.fields.product_placeholder')" />

        <div class="wizard-pills">
            @foreach ($products as $index => $product)
                <span wire:key="product-{{ md5($product) }}" class="wizard-pill">
                    {{ $product }}
                    <button type="button" wire:click="remove({{ $index }})"
                            aria-label="{{ __('wizard.services.remove') }}">×</button>
                </span>
            @endforeach
        </div>

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
