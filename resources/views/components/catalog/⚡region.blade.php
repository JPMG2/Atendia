<?php

use App\Interfaces\Catalog\DataTable;
use App\Livewire\Forms\Catalog\BaseCatalogForm;
use App\Livewire\Forms\Catalog\RegionForm;
use App\Models\Province;
use App\Models\Region;
use App\Traits\InteractsWithCatalogEditor;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Editor for the Regions master (`regions` table).
 *
 * The chrome and the whole Alpine rail live in `<x-catalog.*>` and in
 * `catalogMaster()`, so only the server actions and this master's own fields
 * belong here.
 */
new class extends Component {
    use InteractsWithCatalogEditor;

    public RegionForm $form;

    protected function catalogForm(): BaseCatalogForm
    {
        return $this->form;
    }

    protected function catalogModel(): DataTable
    {
        return new Region;
    }

    /**
     * Province options for the combobox, inactive ones included.
     *
     * Filtering them out would leave the combobox empty on a region that points
     * at a retired province. The label carries the country because province
     * names REPEAT across them — "Córdoba" is in both Argentina and Spain.
     *
     * @return array<int, array{value: int, label: string}>
     */
    #[Computed]
    public function provinceOptions(): array
    {
        return Province::options();
    }
};
?>

<x-catalog.master :rows="$initialRows"
    :search="['name', 'province', 'country']"
    :rules="[
        'name' => ['required', ['minLength', 3], ['maxLength', 255], 'noMarkup'],
        'province_id' => ['required'],
    ]">

    {{-- Table view: the list. --}}
    <x-slot:list>
        <x-catalog.toolbar :search-placeholder="__('catalog.region.search_placeholder')"
            :search-label="__('catalog.region.search_label')" :singular="__('catalog.region.singular')"
            :plural="__('catalog.region.plural')" :create="__('catalog.region.create')" />

        <x-catalog.table :empty="__('catalog.region.empty')" :columns="[
            ['label' => __('catalog.region.columns.name'), 'class' => 'catalog-col-fill'],
            ['label' => __('catalog.region.columns.province')],
            ['label' => __('catalog.region.columns.country')],
            ['label' => __('catalog.region.columns.status')],
        ]">
            <td class="catalog-cell-name catalog-cell-fill" x-text="row.name"></td>
            <td x-text="row.province"></td>
            <td x-text="row.country"></td>
            <td>
                <span class="catalog-status" x-bind:class="row.active ? 'is-on' : 'is-off'">
                    <span class="dot"></span><span
                        x-text="row.active ? {{ \Illuminate\Support\Js::from(__('catalog.region.status.active')) }} : {{ \Illuminate\Support\Js::from(__('catalog.region.status.inactive')) }}"></span>
                </span>
            </td>
        </x-catalog.table>
    </x-slot:list>

    {{-- Form view: create and edit. --}}
    <x-slot:form>
        <x-catalog.form-shell :new="__('catalog.region.new')" :new-title="__('catalog.region.new_title')"
            :edit-title="__('catalog.region.edit_title')" :create="__('catalog.region.create')">

            {{-- Three fields in one row reaching the edge: the name takes the
            slack and the status closes the line. --}}
            <x-catalog.form-row>
                <x-inputsform.input span="text" :label="__('catalog.region.fields.name')" required name="name"
                    :placeholder="__('catalog.region.fields.name_placeholder')" alpine-error="name"
                    wire:model="form.data.name" />

                <x-inputsform.combobox span="text" :label="__('catalog.region.fields.province')" required
                    name="province_id" :placeholder="__('catalog.region.fields.province_placeholder')"
                    :options="$this->provinceOptions" :value="$form->data?->province_id"
                    alpine-error="province_id" wire:model="form.data.province_id" />

                <x-inputsform.switch-field span="short" :label="__('catalog.region.fields.status')" name="is_active"
                    :on="__('catalog.region.status.active')" :off="__('catalog.region.status.inactive')"
                    wire:model="form.data.is_active" />
            </x-catalog.form-row>
        </x-catalog.form-shell>
    </x-slot:form>
</x-catalog.master>
