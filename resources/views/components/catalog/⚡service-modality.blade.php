<?php

use App\Interfaces\Catalog\DataTable;
use App\Livewire\Forms\Catalog\BaseCatalogForm;
use App\Livewire\Forms\Catalog\ServiceModalityForm;
use App\Models\ServiceModality;
use App\Traits\InteractsWithCatalogEditor;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Editor for the Modalities master (`service_modalities` table).
 *
 * The chrome and the whole Alpine rail live in `<x-catalog.*>` and in
 * `catalogMaster()`, so only the server actions and this master's own fields
 * belong here.
 */
new class extends Component {
    use InteractsWithCatalogEditor;

    public ServiceModalityForm $form;

    protected function catalogForm(): BaseCatalogForm
    {
        return $this->form;
    }

    protected function catalogModel(): DataTable
    {
        return new ServiceModality;
    }

    /**
     * Icon options for the combobox: the KEYS of config/icons.php, which is the
     * real glyph catalog. Free text will not do — <x-icon> with a name that
     * does not exist paints a hole.
     *
     * @return array<int, array{value: string, label: string}>
     */
    #[Computed]
    public function iconOptions(): array
    {
        return collect(array_keys(config('icons')))
            ->sort()
            ->map(fn(string $icon): array => ['value' => $icon, 'label' => $icon])
            ->values()
            ->all();
    }
};
?>

<x-catalog.master :rows="$initialRows"
    :search="['code', 'name', 'description']"
    :rules="[
        'code' => ['required', ['minLength', 3], ['maxLength', 30], 'noMarkup'],
        'name' => ['required', ['minLength', 3], ['maxLength', 255], 'noMarkup'],
        'sort_order' => ['integer', ['min', 0], ['max', 32767]],
    ]">

    {{-- Table view: the list. --}}
    <x-slot:list>
        <x-catalog.toolbar :search-placeholder="__('catalog.service_modality.search_placeholder')"
            :search-label="__('catalog.service_modality.search_label')"
            :singular="__('catalog.service_modality.singular')" :plural="__('catalog.service_modality.plural')"
            :create="__('catalog.service_modality.create')" />

        <x-catalog.table :empty="__('catalog.service_modality.empty')" :columns="[
            ['label' => __('catalog.service_modality.columns.code')],
            ['label' => __('catalog.service_modality.columns.name'), 'class' => 'catalog-col-fill'],
            ['label' => __('catalog.service_modality.columns.order')],
            ['label' => __('catalog.service_modality.columns.status')],
        ]">
            <td><span class="catalog-code" x-text="row.code"></span></td>
            {{-- The REAL glyph beside the name instead of a column holding its
            key, which is config and not catalog data. Underneath, what
            the modality asks for and remembers. --}}
            <td class="catalog-cell-fill">
                <span class="catalog-cell-lead">
                    <span class="catalog-row-icon" x-show="row.icon_svg">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round" aria-hidden="true" x-html="row.icon_svg"></svg>
                    </span>
                    <span class="catalog-cell-primary">
                        <span class="name" x-text="row.name"></span>
                        <span class="sub" x-text="row.description"></span>
                    </span>
                </span>
            </td>
            <td class="catalog-cell-sym" x-text="row.order"></td>
            <td>
                <span class="catalog-status" x-bind:class="row.active ? 'is-on' : 'is-off'">
                    <span class="dot"></span><span
                        x-text="row.active ? {{ \Illuminate\Support\Js::from(__('catalog.service_modality.status.active')) }} : {{ \Illuminate\Support\Js::from(__('catalog.service_modality.status.inactive')) }}"></span>
                </span>
            </td>
        </x-catalog.table>
    </x-slot:list>

    {{-- Form view: create and edit. --}}
    <x-slot:form>
        <x-catalog.form-shell :new="__('catalog.service_modality.new')"
            :new-title="__('catalog.service_modality.new_title')"
            :edit-title="__('catalog.service_modality.edit_title')"
            :create="__('catalog.service_modality.create')" title-key="name">

            {{-- Row 1: the short key and the name, which takes all the rest. --}}
            <x-catalog.form-row>
                <x-inputsform.input span="code" :label="__('catalog.service_modality.fields.code')" required name="code"
                    :hint="__('catalog.service_modality.fields.code_hint')" maxlength="30" alpine-error="code"
                    wire:model="form.data.code" />

                <x-inputsform.input span="text" :label="__('catalog.service_modality.fields.name')" required name="name"
                    :placeholder="__('catalog.service_modality.fields.name_placeholder')" alpine-error="name"
                    wire:model="form.data.name" />
            </x-catalog.form-row>

            {{-- Row 2: the description takes the slack and the status closes the
            line, so a boolean does not cost a whole row. --}}
            <x-catalog.form-row>
                <x-inputsform.input span="long" :label="__('catalog.service_modality.fields.description')"
                    name="description" :placeholder="__('catalog.service_modality.fields.description_placeholder')"
                    :hint="__('catalog.service_modality.fields.description_hint')" maxlength="255"
                    alpine-error="description" wire:model="form.data.description" />

                <x-inputsform.combobox span="text" :label="__('catalog.service_modality.fields.icon')" name="icon"
                    :placeholder="__('catalog.service_modality.fields.icon_placeholder')"
                    :hint="__('catalog.service_modality.fields.icon_hint')" :options="$this->iconOptions"
                    :value="$form->data?->icon" alpine-error="icon" wire:model="form.data.icon" />

                <x-inputsform.input span="code" :label="__('catalog.service_modality.fields.order')" name="sort_order"
                    type="number" min="0" max="32767" :hint="__('catalog.service_modality.fields.order_hint')"
                    alpine-error="sort_order" wire:model="form.data.sort_order" />

                <x-inputsform.switch-field span="short" :label="__('catalog.service_modality.fields.status')"
                    name="is_active" :on="__('catalog.service_modality.status.active')"
                    :off="__('catalog.service_modality.status.inactive')" wire:model="form.data.is_active" />
            </x-catalog.form-row>
        </x-catalog.form-shell>
    </x-slot:form>
</x-catalog.master>
