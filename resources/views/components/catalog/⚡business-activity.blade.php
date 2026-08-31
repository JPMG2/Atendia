<?php

use App\Interfaces\Catalog\DataTable;
use App\Livewire\Forms\Catalog\BaseCatalogForm;
use App\Livewire\Forms\Catalog\BusinessActivityForm;
use App\Models\BusinessActivity;
use App\Models\BusinessSector;
use App\Traits\InteractsWithCatalogEditor;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Editor for the Activities master (`business_activities` table).
 *
 * The chrome and the whole Alpine rail live in `<x-catalog.*>` and in
 * `catalogMaster()`, so only the server actions and this master's own fields
 * belong here.
 */
new class extends Component {
    use InteractsWithCatalogEditor;

    public BusinessActivityForm $form;

    protected function catalogForm(): BaseCatalogForm
    {
        return $this->form;
    }

    protected function catalogModel(): DataTable
    {
        return new BusinessActivity;
    }

    /**
     * Sector options for the combobox, inactive ones included.
     *
     * Filtering them out would leave the combobox empty on an activity that
     * points at a retired sector, and saving would then change its sector with
     * nobody touching it.
     *
     * @return array<int, array{value: int, label: string}>
     */
    #[Computed]
    public function sectorOptions(): array
    {
        return BusinessSector::options();
    }
};
?>

<x-catalog.master :rows="$initialRows" :search="['code', 'name', 'sector']"
    :rules="[
        'code' => ['required', ['minLength', 2], ['maxLength', 40], 'noMarkup'],
        'name' => ['required', ['minLength', 3], ['maxLength', 255], 'noMarkup'],
        'business_sector_id' => ['required'],
        'sort_order' => ['integer', ['min', 0], ['max', 32767]],
    ]">

    {{-- Table view: the list. --}}
    <x-slot:list>
        <x-catalog.toolbar :search-placeholder="__('catalog.business_activity.search_placeholder')" :search-label="__('catalog.business_activity.search_label')" :singular="__('catalog.business_activity.singular')" :plural="__('catalog.business_activity.plural')" :create="__('catalog.business_activity.create')" />

        <x-catalog.table :empty="__('catalog.business_activity.empty')" :columns="[
            ['label' => __('catalog.business_activity.columns.code')],
            ['label' => __('catalog.business_activity.columns.name'), 'class' => 'catalog-col-fill'],
            ['label' => __('catalog.business_activity.columns.sector')],
            ['label' => __('catalog.business_activity.columns.order')],
            ['label' => __('catalog.business_activity.columns.status')],
        ]">
            <td><span class="catalog-code" x-text="row.code"></span></td>
            <td class="catalog-cell-name catalog-cell-fill" x-text="row.name"></td>
            <td x-text="row.sector"></td>
            <td class="catalog-cell-sym" x-text="row.order"></td>
            <td>
                <span class="catalog-status" x-bind:class="row.active ? 'is-on' : 'is-off'">
                    <span class="dot"></span><span
                        x-text="row.active ? {{ \Illuminate\Support\Js::from(__('catalog.business_activity.status.active')) }} : {{ \Illuminate\Support\Js::from(__('catalog.business_activity.status.inactive')) }}"></span>
                </span>
            </td>
        </x-catalog.table>
    </x-slot:list>

    {{-- Form view: create and edit. --}}
    <x-slot:form>
        <x-catalog.form-shell :new="__('catalog.business_activity.new')" :new-title="__('catalog.business_activity.new_title')" :edit-title="__('catalog.business_activity.edit_title')" :create="__('catalog.business_activity.create')"
            title-key="name">

            {{-- Row 1: the short key and the name, which takes all the rest. --}}
            <x-catalog.form-row>
                <x-inputsform.input span="code" :label="__('catalog.business_activity.fields.code')" required name="code" :hint="__('catalog.business_activity.fields.code_hint')"
                    maxlength="40" alpine-error="code" wire:model="form.data.code" />

                <x-inputsform.input span="text" :label="__('catalog.business_activity.fields.name')" required name="name" :placeholder="__('catalog.business_activity.fields.name_placeholder')"
                    alpine-error="name" wire:model="form.data.name" />
            </x-catalog.form-row>

            {{-- Row 2: the sector it hangs off, the description taking the slack,
            then the order and the status closing the line. --}}
            <x-catalog.form-row>
                <x-inputsform.combobox span="text" :label="__('catalog.business_activity.fields.sector')" required name="business_sector_id"
                    :placeholder="__('catalog.business_activity.fields.sector_placeholder')" :options="$this->sectorOptions" :value="$form->data?->business_sector_id" alpine-error="business_sector_id"
                    wire:model="form.data.business_sector_id" />

                <x-inputsform.input span="long" :label="__('catalog.business_activity.fields.description')" name="description" :placeholder="__('catalog.business_activity.fields.description_placeholder')"
                    :hint="__('catalog.business_activity.fields.description_hint')" maxlength="255" alpine-error="description"
                    wire:model="form.data.description" />

                <x-inputsform.input span="code" :label="__('catalog.business_activity.fields.order')" name="sort_order" type="number" min="0"
                    max="32767" :hint="__('catalog.business_activity.fields.order_hint')" alpine-error="sort_order"
                    wire:model="form.data.sort_order" />

                <x-inputsform.switch-field span="short" :label="__('catalog.business_activity.fields.status')" name="is_active" :on="__('catalog.business_activity.status.active')"
                    :off="__('catalog.business_activity.status.inactive')" wire:model="form.data.is_active" />
            </x-catalog.form-row>
        </x-catalog.form-shell>
    </x-slot:form>
</x-catalog.master>
