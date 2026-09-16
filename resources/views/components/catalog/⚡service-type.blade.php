<?php

use App\Interfaces\Catalog\DataTable;
use App\Livewire\Forms\Catalog\BaseCatalogForm;
use App\Livewire\Forms\Catalog\ServiceTypeForm;
use App\Models\BusinessSector;
use App\Models\ServiceAttribute;
use App\Models\ServiceModality;
use App\Models\ServiceType;
use App\Traits\InteractsWithCatalogEditor;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Editor for the Service types master (`service_types` table).
 *
 * The chrome and the Alpine rail live in `<x-catalog.*>` and `catalogMaster()`.
 * The form also owns the type's ATTRIBUTE SET: one pivot row per assigned
 * attribute, with required/label/help of this instance, reconciled on save.
 */
new class extends Component
{
    use InteractsWithCatalogEditor;

    public ServiceTypeForm $form;

    protected function catalogForm(): BaseCatalogForm
    {
        return $this->form;
    }

    protected function catalogModel(): DataTable
    {
        return new ServiceType;
    }

    /**
     * Modality options for the combobox, inactive ones included.
     *
     * Filtering them out would leave the combobox empty on a type that points
     * at a retired modality, and saving would then change its modality with
     * nobody touching it.
     *
     * @return array<int, array{value: int, label: string}>
     */
    #[Computed]
    public function modalityOptions(): array
    {
        return ServiceModality::options();
    }

    /**
     * Sector options for the combobox. Same reasoning as above.
     *
     * @return array<int, array{value: int, label: string}>
     */
    #[Computed]
    public function sectorOptions(): array
    {
        return BusinessSector::options();
    }

    /**
     * Attribute options for the set rows, inactive included: hiding a
     * retired one would blank a pivot row that still points at it.
     *
     * @return array<int, array{value: int, label: string}>
     */
    #[Computed]
    public function attributeOptions(): array
    {
        return ServiceAttribute::options();
    }

    public function addAttributeRow(): void
    {
        $this->form->addAttributeRow();
    }

    public function removeAttributeRow(int $index): void
    {
        $this->form->removeAttributeRow($index);
    }

    /** wire:sort hands the dragged row's index and its new position. */
    public function reorderAttributeRows(int $index, int $position): void
    {
        $this->form->moveAttributeRow($index, $position);
    }

    public function applyToSector(): void
    {
        $this->dispatchNotification($this->form->applyToSector());
    }
};
?>

<x-catalog.master
    :rows="$initialRows"
    :search="['code', 'name', 'modality', 'sector', 'attributes']"
    :rules="[
        'code' => ['required', ['minLength', 3], ['maxLength', 40], 'noMarkup'],
        'name' => ['required', ['minLength', 3], ['maxLength', 255], 'noMarkup'],
        'service_modality_id' => ['required'],
        'sort_order' => ['integer', ['min', 0], ['max', 32767]],
    ]"
>
    {{-- Table view: the list. --}}
    <x-slot:list>
        <x-catalog.toolbar
            :search-placeholder="__('catalog.service_type.search_placeholder')"
            :search-label="__('catalog.service_type.search_label')"
            :singular="__('catalog.service_type.singular')"
            :plural="__('catalog.service_type.plural')"
            :create="__('catalog.service_type.create')"
        />

        <x-catalog.table
            :empty="__('catalog.service_type.empty')"
            :columns="[
                ['label' => __('catalog.service_type.columns.code')],
                ['label' => __('catalog.service_type.columns.name'), 'class' => 'catalog-col-fill'],
                ['label' => __('catalog.service_type.columns.modality')],
                ['label' => __('catalog.service_type.columns.attributes')],
                ['label' => __('catalog.service_type.columns.status')],
            ]"
        >
            <td><span class="catalog-code" x-text="row.code"></span></td>
            {{-- The sector rides along with the description on the second line
            rather than in a column: it is screen grouping and not an
            attribute, and a column pushed the table out of the panel. --}}
            <td class="catalog-cell-fill">
                <span class="catalog-cell-primary">
                    <span class="name" x-text="row.name"></span>
                    <span class="sub">
                        <span class="tag" x-show="row.sector" x-text="row.sector"></span>
                        <span x-text="row.description"></span>
                    </span>
                </span>
            </td>
            <td x-text="row.modality"></td>
            <td>
                <span class="catalog-chips">
                    <template x-for="attribute in row.attributes" :key="attribute">
                        <span class="catalog-chip" x-text="attribute"></span>
                    </template>
                </span>
            </td>
            <td>
                <span class="catalog-status" x-bind:class="row.active ? 'is-on' : 'is-off'">
                    <span class="dot"></span
                    ><span
                        x-text="row.active ? {{ \Illuminate\Support\Js::from(__('catalog.service_type.status.active')) }} : {{ \Illuminate\Support\Js::from(__('catalog.service_type.status.inactive')) }}"
                    ></span>
                </span>
            </td>
        </x-catalog.table>
    </x-slot:list>

    {{-- Form view: create and edit. --}}
    <x-slot:form>
        <x-catalog.form-shell
            :new="__('catalog.service_type.new')"
            :new-title="__('catalog.service_type.new_title')"
            :edit-title="__('catalog.service_type.edit_title')"
            :create="__('catalog.service_type.create')"
            title-key="name"
        >
            {{-- Row 1: the short key, the name and how it is offered. --}}
            <x-catalog.form-row>
                <x-inputsform.input
                    span="code"
                    :label="__('catalog.service_type.fields.code')"
                    required
                    name="code"
                    :hint="__('catalog.service_type.fields.code_hint')"
                    maxlength="40"
                    alpine-error="code"
                    wire:model="form.data.code"
                />

                <x-inputsform.input
                    span="text"
                    :label="__('catalog.service_type.fields.name')"
                    required
                    name="name"
                    :placeholder="__('catalog.service_type.fields.name_placeholder')"
                    alpine-error="name"
                    wire:model="form.data.name"
                />

                <x-inputsform.combobox
                    span="text"
                    :label="__('catalog.service_type.fields.modality')"
                    required
                    name="service_modality_id"
                    :placeholder="__('catalog.service_type.fields.modality_placeholder')"
                    :hint="__('catalog.service_type.fields.modality_hint')"
                    :options="$this->modalityOptions"
                    :value="$form->data?->service_modality_id"
                    alpine-error="service_modality_id"
                    wire:model="form.data.service_modality_id"
                />
            </x-catalog.form-row>

            {{-- Row 2: the description takes the slack and the status closes the
            line, so a boolean does not cost a whole row. --}}
            <x-catalog.form-row>
                <x-inputsform.input
                    span="long"
                    :label="__('catalog.service_type.fields.description')"
                    name="description"
                    :placeholder="__('catalog.service_type.fields.description_placeholder')"
                    :hint="__('catalog.service_type.fields.description_hint')"
                    maxlength="255"
                    alpine-error="description"
                    wire:model="form.data.description"
                />

                <x-inputsform.combobox
                    span="text"
                    :label="__('catalog.service_type.fields.sector')"
                    name="business_sector_id"
                    :placeholder="__('catalog.service_type.fields.sector_placeholder')"
                    :hint="__('catalog.service_type.fields.sector_hint')"
                    :options="$this->sectorOptions"
                    :value="$form->data?->business_sector_id"
                    alpine-error="business_sector_id"
                    wire:model="form.data.business_sector_id"
                />

                <x-inputsform.input
                    span="code"
                    :label="__('catalog.service_type.fields.order')"
                    name="sort_order"
                    type="number"
                    min="0"
                    max="32767"
                    :hint="__('catalog.service_type.fields.order_hint')"
                    alpine-error="sort_order"
                    wire:model="form.data.sort_order"
                />

                <x-inputsform.switch-field
                    span="short"
                    :label="__('catalog.service_type.fields.status')"
                    name="is_active"
                    :on="__('catalog.service_type.status.active')"
                    :off="__('catalog.service_type.status.inactive')"
                    wire:model="form.data.is_active"
                />
            </x-catalog.form-row>

            {{-- The attribute set (Magento pattern): what belongs to THIS
            instance — required, order by position, label — lives on the
            pivot row, never on the attribute. Reconciles on save. --}}
            <div class="mt-4 border-t border-[color:var(--border-subtle)] pt-4">
                <h3 class="text-strong font-display text-base font-bold">{{ __('catalog.service_type.attributes.title') }}</h3>
                <p class="text-muted text-sm">{{ __('catalog.service_type.attributes.hint') }}</p>
            </div>

            {{-- Draggable by the grip: the row position IS the sheet order. --}}
            <div wire:sort="reorderAttributeRows">
                @foreach ($form->attributeRows as $index => $row)
                    <div wire:sort:item="{{ $index }}" wire:key="attr-row-{{ $index }}" class="flex items-center gap-1">
                        <span wire:sort:handle class="cursor-grab flex-none px-1 text-subtle">
                            <x-icon name="grip-vertical" :size="16" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <x-catalog.form-row>
                                <x-inputsform.combobox
                                    span="text"
                                    :label="__('catalog.service_type.attributes.field')"
                                    name="attribute_rows.{{ $index }}.service_attribute_id"
                                    :placeholder="__('catalog.service_type.attributes.placeholder')"
                                    :options="$this->attributeOptions"
                                    :value="$row['service_attribute_id']"
                                    wire:model="form.attributeRows.{{ $index }}.service_attribute_id"
                                />
                                <x-inputsform.input
                                    span="short"
                                    :label="__('catalog.service_type.attributes.label')"
                                    name="attribute_rows.{{ $index }}.label_override"
                                    :placeholder="__('catalog.service_type.attributes.label_placeholder')"
                                    wire:model="form.attributeRows.{{ $index }}.label_override"
                                />
                                <x-inputsform.input
                                    span="long"
                                    :label="__('catalog.service_type.attributes.hint_field')"
                                    name="attribute_rows.{{ $index }}.hint_override"
                                    :placeholder="__('catalog.service_type.attributes.hint_placeholder')"
                                    wire:model="form.attributeRows.{{ $index }}.hint_override"
                                />
                                <x-inputsform.switch-field
                                    span="code"
                                    :label="__('catalog.service_type.attributes.required')"
                                    name="attribute_rows.{{ $index }}.is_required"
                                    :on="__('catalog.service_type.attributes.required_on')"
                                    :off="__('catalog.service_type.attributes.required_off')"
                                    wire:model="form.attributeRows.{{ $index }}.is_required"
                                />
                                <div class="flex flex-none items-end self-stretch pb-1.5">
                                    <x-ui.icon-button
                                        icon="x"
                                        size="sm"
                                        variant="ghost"
                                        wire:click="removeAttributeRow({{ $index }})"
                                        :label="__('catalog.service_type.attributes.remove')"
                                    />
                                </div>
                            </x-catalog.form-row>
                        </div>
                    </div>
                @endforeach
            </div>

            <div>
                <x-ui.button variant="ghost" size="sm" icon="plus" wire:click="addAttributeRow">
                    {{ __('catalog.service_type.attributes.add') }}
                </x-ui.button>
            </div>

            {{-- Reaches the whole trade in one move; nobody is forced —
            the catalog suggests, never obliges (GBP pattern). --}}
            @if ($form->recordId !== null)
                <div class="mt-2 flex flex-wrap items-center gap-3 border-t border-[color:var(--border-subtle)] pt-3">
                    <p class="flex-1 text-sm text-muted">{{ __('catalog.service_type.suggest.hint') }}</p>
                    <x-ui.button variant="secondary" size="sm" icon="zap" wire:click="applyToSector">
                        {{ __('catalog.service_type.suggest.button') }}
                    </x-ui.button>
                </div>
            @endif
        </x-catalog.form-shell>
    </x-slot:form>
</x-catalog.master>
