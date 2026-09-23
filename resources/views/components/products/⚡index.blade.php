<?php

use App\Classes\Main\Client;
use App\Classes\Main\Inventory;
use App\Enums\NotificationType;
use App\Livewire\Forms\Client\ProductForm;
use App\Models\Product;
use App\Models\ProductImport;
use App\Traits\HasNotifications;
use App\Traits\ImportsProducts;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * "Tus productos" — the real screen over the tenant's rows. Same
 * Fresha/Shopify split as the services screen: the LIST finds and operates,
 * the SHEET edits in a slide-over, and the spreadsheet lane runs the shared
 * {@see ImportsProducts} flow. State, validation and saves live in the
 * Form; queries in the Inventory piece.
 */
new class extends Component
{
    use HasNotifications;
    use ImportsProducts;
    use WithFileUploads;

    public ProductForm $form;

    public string $search = '';

    public string $filter = 'all';

    /** Rows on screen; "load more" grows it by a page. */
    public int $visible = 8;

    public bool $sheetOpen = false;

    public function mount(): void
    {
        // The Wireable DTO must exist before hydration, or it type-errors.
        $this->form->setup();
    }

    private function inventory(): ?Inventory
    {
        return Client::for(Auth::user())->inventory;
    }

    /** @return Collection<int, Product> */
    #[Computed]
    public function products(): Collection
    {
        return $this->inventory()?->products ?? new Collection;
    }

    /** The newest finished import — the lane's footnote. */
    #[Computed]
    public function lastImport(): ?ProductImport
    {
        return $this->inventory()?->lastImport;
    }

    /**
     * Search (name or code) and availability filter over the full set.
     *
     * @return Collection<int, Product>
     */
    #[Computed]
    public function filtered(): Collection
    {
        $needle = Str::ascii(mb_strtolower(trim($this->search)));

        return $this->products->filter(function (Product $product) use ($needle): bool {
            if ($this->filter === 'available' && ! $product->in_stock) {
                return false;
            }
            if ($this->filter === 'out' && $product->in_stock) {
                return false;
            }

            return $needle === ''
                || str_contains(Str::ascii(mb_strtolower($product->name)), $needle)
                || str_contains(Str::ascii(mb_strtolower($product->code ?? '')), $needle);
        });
    }

    /** @return list<Product> The window the list actually paints. */
    #[Computed]
    public function rows(): array
    {
        return $this->filtered->values()->take($this->visible)->all();
    }

    public function loadMore(): void
    {
        $this->visible += 8;
    }

    /** A new search or filter restarts the window at the top. */
    public function updatedSearch(): void
    {
        $this->visible = 8;
    }

    public function updatedFilter(): void
    {
        $this->visible = 8;
    }

    /** @return array<int, string> The sheet's type picker, producto types only. */
    #[Computed]
    public function typeOptions(): array
    {
        return $this->inventory()?->typeOptions ?? [];
    }

    /** @return list<array<string, mixed>> The picked type's own fields. */
    #[Computed]
    public function sheetAttributes(): array
    {
        return $this->inventory()?->attributeSet($this->form->data->service_type_id) ?? [];
    }

    public function edit(int $id): void
    {
        $this->form->setup($id);
        $this->sheetOpen = true;
    }

    public function add(): void
    {
        $this->form->setup();
        $this->sheetOpen = true;
    }

    public function closeSheet(): void
    {
        $this->sheetOpen = false;
    }

    public function saveProduct(): void
    {
        $notification = $this->form->save();
        $this->dispatchNotification($notification);

        if ($notification->type !== NotificationType::Error) {
            $this->sheetOpen = false;
            $this->refreshList();
        }
    }

    /** The row flips in stock / out of stock; the switch is the feedback. */
    public function toggle(int $id): void
    {
        $this->inventory()?->toggle($id);
        $this->refreshList();
    }

    /** The upload's rules live on the Form, never inline in the component. */
    protected function validateImportUpload(mixed $file): void
    {
        $this->form->validateImportUpload($file);
    }

    /** A sync queue may have written the rows already: repaint the list. */
    protected function importQueued(ProductImport $import): void
    {
        $this->refreshList();
    }

    /** Busts every computed that reads the tenant's rows after a write. */
    private function refreshList(): void
    {
        unset($this->products, $this->filtered, $this->rows, $this->lastImport);
    }
};
?>

<div>
    <div class="page-head">
        <div>
            <h1 class="page-head-title">{{ __('client.products.title') }}</h1>
            <p class="page-head-sub">{{ __('client.products.sub') }}</p>
        </div>
    </div>

    {{-- The sheet slides over the list: editing never loses the place. --}}
    @if ($sheetOpen)
        <x-ui.slide-over
            x-on:slide-over-close="$wire.closeSheet()"
            :title="$form->editingId !== null ? __('client.products.sheet_title', ['name' => $form->data->name]) : __('client.products.sheet_new_title')"
            :subtitle="__('client.products.sheet_hint')"
        >
            <div class="flex flex-col gap-4">
                <x-catalog.form-row>
                    <x-inputsform.input span="full" name="name" :label="__('client.products.field_name')"
                        wire:model="form.data.name" :value="$form->data->name" />
                </x-catalog.form-row>
                <x-catalog.form-row>
                    <x-inputsform.input span="code" name="code" :label="__('client.products.field_code')"
                        wire:model="form.data.code" :value="$form->data->code" class="font-mono" />
                    <x-inputsform.input span="short" name="price" :label="__('client.products.field_price')"
                        wire:model="form.data.price" :value="$form->data->price" class="font-mono" inputmode="numeric" />
                    <x-inputsform.input span="text" name="stock" :label="__('client.products.field_stock')"
                        :hint="__('client.products.stock_help')"
                        wire:model="form.data.stock" :value="$form->data->stock" class="font-mono" inputmode="numeric" />
                </x-catalog.form-row>
                {{-- The catalog mould: picking one grows the sheet with ITS
                fields, curated in the admin catalog — the sheet adapts,
                code never changes. --}}
                @if ($this->typeOptions !== [])
                    <x-catalog.form-row>
                        <x-inputsform.combobox span="full" name="service_type_id" :label="__('client.products.field_type')"
                            :hint="__('client.products.type_help')"
                            wire:model.live="form.data.service_type_id"
                            :value="$form->data->service_type_id"
                            :options="$this->typeOptions" />
                    </x-catalog.form-row>
                    <x-client.attribute-fields :set="$this->sheetAttributes" :values="$form->data->attribute_values" />
                @endif
                <x-catalog.form-row>
                    <x-inputsform.textarea span="full" name="description" :rows="3" :label="__('client.products.field_description')"
                            wire:model="form.data.description"
                            :hint="__('client.products.description_help')">{{ $form->data->description }}</x-inputsform.textarea>
                </x-catalog.form-row>
                <x-ui.switch name="in_stock" :label="__('client.products.sheet_available')"
                    wire:model="form.data.in_stock" :checked="$form->data->in_stock" />
            </div>

            <x-slot:footer>
                {{-- Cancel wears the danger colour by the owner's call
                (2026-09-15): the universal "watch out" cue. --}}
                <x-ui.button variant="danger" size="sm" wire:click="closeSheet">{{ __('client.products.sheet_cancel') }}</x-ui.button>
                <span class="flex-1"></span>
                <x-ui.button variant="primary" size="sm" wire:click="saveProduct">{{ __('client.products.sheet_save') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.slide-over>
    @endif

    @if ($this->products->isEmpty())
        @if ($headers === [])
            {{-- The empty state leads with the excel: a spreadsheet of
            hundreds must never feel like typing them one by one. --}}
            <x-client.offer-empty
                icon="package"
                :title="__('client.products.empty_title')"
                :body="__('client.products.empty_body')"
            >
                <div class="w-full max-w-xl">
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
                </div>
                @if ($queuedFile !== null)
                    <p class="font-mono text-sm text-brand">{{ __('client.products.import_queued', ['file' => $queuedFile, 'rows' => $totalRows]) }}</p>
                @endif
                <p class="text-sm text-subtle">{{ __('client.products.empty_or') }}</p>
                <x-ui.button variant="primary" size="sm" icon="plus" wire:click="add">{{ __('client.products.add') }}</x-ui.button>
            </x-client.offer-empty>
        @else
            <x-ui.card class="p-5">
                <x-client.import-review
                    :headers="$headers"
                    :labels="$labels"
                    :mapping="$mapping"
                    :fix-originals="$fixOriginals"
                    :fixes="$fixes"
                    :total-rows="$totalRows"
                    :target-options="$this->targetOptions"
                />
            </x-ui.card>
        @endif
    @else
        <div class="flex flex-col gap-3">
            {{-- Same humanity note as the services screen, aimed at THIS
            screen's magic moment: the list the owner already keeps, loaded
            without typing. --}}
            <x-ui.ai-banner
                :title="__('client.products.ai_title')"
                :body="__('client.products.ai_body')"
                :action="__('client.products.ai_action')"
            />

            {{-- ONE surface: toolbar to find, list to act; the sheet slides over. --}}
            <x-ui.card class="p-5">
                {{-- A declared row: the search absorbs the slack so the toolbar
                always reaches the right edge (golden rule, formularios §5). --}}
                <x-catalog.form-row>
                    <x-inputsform.input span="long" name="list_search" icon="search"
                        wire:model.live.debounce.300ms="search"
                        :aria-label="__('client.products.search_placeholder')"
                        :placeholder="__('client.products.search_placeholder')" />
                    <x-inputsform.combobox span="short" name="filter" wire:model.live="filter"
                        :value="$filter" :placeholder="__('client.products.filter_label')" :options="[
                            'all' => __('client.products.filter_all'),
                            'available' => __('client.products.filter_available'),
                            'out' => __('client.products.filter_out'),
                        ]" />
                    <div class="flex flex-none items-center self-center">
                        <x-ui.button variant="primary" size="sm" icon="plus" wire:click="add">{{ __('client.products.add') }}</x-ui.button>
                    </div>
                </x-catalog.form-row>

                <p class="mt-3 border-b border-[color:var(--border-subtle)] pb-3 font-mono text-sm text-subtle">
                    {{ trans_choice('client.products.count', $this->filtered->count(), ['count' => $this->filtered->count()]) }}
                </p>

                @if ($this->rows === [])
                    <p class="py-6 text-center text-sm text-muted">{{ __('client.products.no_results') }}</p>
                @else
                    {{-- Out of stock switches OFF, never out: the assistant
                    answers "sin stock por ahora" and the row stays. --}}
                    <ul class="divide-y divide-[color:var(--border-subtle)]">
                        @foreach ($this->rows as $product)
                            <li class="flex flex-wrap items-center gap-x-4 gap-y-1 rounded-lg px-2 py-2.5 transition hover:bg-sunken" wire:key="product-{{ $product->id }}">
                                <div class="min-w-0 flex-1">
                                    <p @class(['text-sm font-semibold', 'text-strong' => $product->in_stock, 'text-muted' => ! $product->in_stock])>
                                        <x-ui.match :text="$product->name" :needle="$search" />
                                    </p>
                                    @if (! $product->in_stock)
                                        <p class="text-xs text-muted">{{ __('client.products.out_note') }}</p>
                                    @endif
                                </div>
                                <p class="hidden font-mono text-xs text-subtle sm:block">
                                    @if ($product->code !== null)
                                        <x-ui.match :text="$product->code" :needle="$search" />
                                    @else
                                        —
                                    @endif
                                </p>
                                <p class="font-mono text-sm font-bold text-strong">{{ $product->price !== null ? '$ '.number_format((float) $product->price, 0, ',', '.') : '—' }}</p>
                                <x-ui.switch name="available_{{ $product->id }}" :checked="$product->in_stock" size="sm"
                                    wire:change="toggle({{ $product->id }})"
                                    :aria-label="__('client.products.toggle_stock', ['name' => $product->name])" />
                                <x-ui.icon-button icon="pencil" size="sm" variant="ghost" wire:click="edit({{ $product->id }})"
                                    :label="__('client.products.edit', ['name' => $product->name])" />
                            </li>
                        @endforeach
                    </ul>

                    <x-ui.load-more :shown="count($this->rows)" :total="$this->filtered->count()" action="loadMore" />
                @endif
            </x-ui.card>

            {{-- The import lane lives beside the list, not inside it; the
            review takes its place while a fresh file waits to be confirmed. --}}
            <x-ui.card class="p-5">
                @if ($headers === [])
                    <div class="flex flex-wrap items-center gap-4">
                        <div class="flex size-11 items-center justify-center rounded-xl bg-brand-soft">
                            <x-icon name="upload" :size="20" style="color:var(--brand)" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <h2 class="font-display text-base font-bold text-strong">{{ __('client.products.import_title') }}</h2>
                            <p class="text-sm text-muted">{{ __('client.products.import_body') }}</p>
                        </div>
                    </div>
                    <div class="mt-3">
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
                    </div>
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
                    <p class="mt-3 border-t border-[color:var(--border-subtle)] pt-3 font-mono text-xs text-brand">
                        {{ __('client.products.import_queued', ['file' => $queuedFile, 'rows' => $totalRows]) }}
                    </p>
                @elseif ($this->lastImport !== null)
                    <p class="mt-3 border-t border-[color:var(--border-subtle)] pt-3 font-mono text-xs text-subtle">
                        {{ __('client.products.import_last', ['file' => $this->lastImport->original_name, 'rows' => $this->lastImport->total_rows]) }}
                    </p>
                @endif
            </x-ui.card>
        </div>
    @endif

</div>
