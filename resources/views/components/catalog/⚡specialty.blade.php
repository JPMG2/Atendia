<?php

use App\Interfaces\Catalog\DataTable;
use App\Livewire\Forms\Catalog\BaseCatalogForm;
use App\Livewire\Forms\Catalog\SpecialtyForm;
use App\Models\BusinessActivity;
use App\Models\Specialty;
use App\Traits\InteractsWithCatalogEditor;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Editor for the Specialties master (`specialties` table).
 *
 * The chrome and the whole Alpine rail live in `<x-catalog.*>` and in
 * `catalogMaster()`, so only the server actions and this master's own fields
 * belong here.
 */
new class extends Component
{
    use InteractsWithCatalogEditor;

    public SpecialtyForm $form;

    protected function catalogForm(): BaseCatalogForm
    {
        return $this->form;
    }

    protected function catalogModel(): DataTable
    {
        return new Specialty;
    }

    /**
     * Activity options for the combobox, inactive ones included.
     *
     * Filtering them out would leave the combobox empty on a specialty that
     * points at a retired activity, and saving would then change its activity
     * with nobody touching it.
     *
     * @return array<int, array{value: int, label: string}>
     */
    #[Computed]
    public function activityOptions(): array
    {
        return BusinessActivity::options();
    }
};
?>

<x-catalog.master
    :rows="$initialRows"
    :search="$this->searchKeys"
    :rules="[
        'code' => ['required', ['minLength', 2], ['maxLength', 60], 'noMarkup'],
        'name' => ['required', ['minLength', 3], ['maxLength', 255], 'noMarkup'],
        'business_activity_id' => ['required'],
        'sort_order' => ['integer', ['min', 0], ['max', 32767]],
    ]"
>
    {{-- Table view: the list. --}}
    <x-slot:list>
        <x-catalog.toolbar
            :search-placeholder="__('catalog.specialty.search_placeholder')"
            :search-label="__('catalog.specialty.search_label')"
            :singular="__('catalog.specialty.singular')"
            :plural="__('catalog.specialty.plural')"
            :create="__('catalog.specialty.create')"
        />

        <x-catalog.table
            :empty="__('catalog.specialty.empty')"
            :columns="$this->tableColumns"
        >
            <td><span class="catalog-code" x-text="row.code"></span></td>
            <td class="catalog-cell-name catalog-cell-fill" x-text="row.name"></td>
            <td x-text="row.activity"></td>
            <td class="catalog-cell-sym" x-text="row.order"></td>
            <td>
                <span class="catalog-status" x-bind:class="row.active ? 'is-on' : 'is-off'">
                    <span class="dot"></span
                    ><span
                        x-text="row.active ? {{ \Illuminate\Support\Js::from(__('catalog.specialty.status.active')) }} : {{ \Illuminate\Support\Js::from(__('catalog.specialty.status.inactive')) }}"
                    ></span>
                </span>
            </td>
        </x-catalog.table>
    </x-slot:list>

    {{-- Form view: create and edit. --}}
    <x-slot:form>
        <x-catalog.form-shell
            :new="__('catalog.specialty.new')"
            :new-title="__('catalog.specialty.new_title')"
            :edit-title="__('catalog.specialty.edit_title')"
            :create="__('catalog.specialty.create')"
            title-key="name"
        >
            {{-- Row 1: the key and the name. The key is text-wide here: "medicina-antienvejecimiento" must not be cut. --}}
            <x-catalog.form-row>
                <x-inputsform.input
                    span="text"
                    :label="__('catalog.specialty.fields.code')"
                    required
                    name="code"
                    :hint="__('catalog.specialty.fields.code_hint')"
                    maxlength="60"
                    alpine-error="code"
                    wire:model="form.data.code"
                />

                <x-inputsform.input
                    span="text"
                    :label="__('catalog.specialty.fields.name')"
                    required
                    name="name"
                    :placeholder="__('catalog.specialty.fields.name_placeholder')"
                    alpine-error="name"
                    wire:model="form.data.name"
                />
            </x-catalog.form-row>

            {{-- Row 2: the activity it hangs off, then the order and the status closing the line. --}}
            <x-catalog.form-row>
                <x-inputsform.combobox
                    span="long"
                    :label="__('catalog.specialty.fields.activity')"
                    required
                    name="business_activity_id"
                    :placeholder="__('catalog.specialty.fields.activity_placeholder')"
                    :hint="__('catalog.specialty.fields.activity_hint')"
                    :options="$this->activityOptions"
                    :value="$form->data?->business_activity_id"
                    alpine-error="business_activity_id"
                    wire:model="form.data.business_activity_id"
                />

                <x-inputsform.input
                    span="code"
                    :label="__('catalog.specialty.fields.order')"
                    name="sort_order"
                    type="number"
                    min="0"
                    max="32767"
                    :hint="__('catalog.specialty.fields.order_hint')"
                    alpine-error="sort_order"
                    wire:model="form.data.sort_order"
                />

                <x-inputsform.switch-field
                    span="short"
                    :label="__('catalog.specialty.fields.status')"
                    name="is_active"
                    :on="__('catalog.specialty.status.active')"
                    :off="__('catalog.specialty.status.inactive')"
                    wire:model="form.data.is_active"
                />
            </x-catalog.form-row>
        </x-catalog.form-shell>
    </x-slot:form>
</x-catalog.master>
