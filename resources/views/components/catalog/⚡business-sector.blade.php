<?php

use App\Interfaces\Catalog\DataTable;
use App\Livewire\Forms\Catalog\BaseCatalogForm;
use App\Livewire\Forms\Catalog\BusinessSectorForm;
use App\Models\BusinessSector;
use App\Traits\InteractsWithCatalogEditor;
use Livewire\Component;

/**
 * Editor del maestro Rubros (tabla `business_sectors`).
 *
 * El chrome (toolbar, tabla, barra del form, pie de acciones) y todo el riel de
 * Alpine viven en `<x-catalog.*>` y en `catalogMaster()`: acá quedan SOLO las
 * acciones del server y los campos propios del maestro. Livewire 4 nativo (SFC).
 */
new class extends Component {
    use InteractsWithCatalogEditor;

    public BusinessSectorForm $form;

    protected function catalogForm(): BaseCatalogForm
    {
        return $this->form;
    }

    protected function catalogModel(): DataTable
    {
        return new BusinessSector;
    }
};
?>

<x-catalog.master :rows="$initialRows"
    :search="['code', 'name', 'description']"
    :rules="[
        'code' => ['required', ['minLength', 2], ['maxLength', 30], 'noMarkup'],
        'name' => ['required', ['minLength', 3], ['maxLength', 255], 'noMarkup'],
        'sort_order' => ['integer', ['min', 0], ['max', 32767]],
    ]">

    {{-- ============ VISTA TABLA (el "mostrar") ============ --}}
    <x-slot:list>
        <x-catalog.toolbar :search-placeholder="__('catalog.business_sector.search_placeholder')"
            :search-label="__('catalog.business_sector.search_label')"
            :singular="__('catalog.business_sector.singular')" :plural="__('catalog.business_sector.plural')"
            :create="__('catalog.business_sector.create')" />

        <x-catalog.table :empty="__('catalog.business_sector.empty')" :columns="[
            ['label' => __('catalog.business_sector.columns.code')],
            ['label' => __('catalog.business_sector.columns.name')],
            ['label' => __('catalog.business_sector.columns.description'), 'class' => 'catalog-col-fill'],
            ['label' => __('catalog.business_sector.columns.order')],
            ['label' => __('catalog.business_sector.columns.status')],
        ]">
            <td><span class="catalog-code" x-text="row.code"></span></td>
            <td class="catalog-cell-name" x-text="row.name"></td>
            <td class="catalog-cell-fill" x-text="row.description"></td>
            <td class="catalog-cell-sym" x-text="row.order"></td>
            <td>
                <span class="catalog-status" x-bind:class="row.active ? 'is-on' : 'is-off'">
                    <span class="dot"></span><span
                        x-text="row.active ? {{ \Illuminate\Support\Js::from(__('catalog.business_sector.status.active')) }} : {{ \Illuminate\Support\Js::from(__('catalog.business_sector.status.inactive')) }}"></span>
                </span>
            </td>
        </x-catalog.table>
    </x-slot:list>

    {{-- ============ VISTA FORMULARIO (crear / editar) ============ --}}
    <x-slot:form>
        <x-catalog.form-shell :new="__('catalog.business_sector.new')"
            :new-title="__('catalog.business_sector.new_title')" :edit-title="__('catalog.business_sector.edit_title')"
            :create="__('catalog.business_sector.create')" title-key="name">

            {{-- Fila 1: la clave corta y el nombre, que se lleva todo el resto. --}}
            <x-catalog.form-row>
                <x-inputsform.input span="code" :label="__('catalog.business_sector.fields.code')" required name="code"
                    :hint="__('catalog.business_sector.fields.code_hint')" maxlength="30" alpine-error="code"
                    wire:model="form.data.code" />

                <x-inputsform.input span="text" :label="__('catalog.business_sector.fields.name')" required name="name"
                    :placeholder="__('catalog.business_sector.fields.name_placeholder')" alpine-error="name"
                    wire:model="form.data.name" />
            </x-catalog.form-row>

            {{-- Fila 2: la descripción absorbe el sobrante y el estado cierra la
                 línea, para no gastar una fila entera en un booleano. --}}
            <x-catalog.form-row>
                <x-inputsform.input span="long" :label="__('catalog.business_sector.fields.description')"
                    name="description" :placeholder="__('catalog.business_sector.fields.description_placeholder')"
                    :hint="__('catalog.business_sector.fields.description_hint')" maxlength="255"
                    alpine-error="description" wire:model="form.data.description" />

                <x-inputsform.input span="code" :label="__('catalog.business_sector.fields.order')" name="sort_order"
                    type="number" min="0" max="32767" :hint="__('catalog.business_sector.fields.order_hint')"
                    alpine-error="sort_order" wire:model="form.data.sort_order" />

                <x-inputsform.switch-field span="short" :label="__('catalog.business_sector.fields.status')"
                    name="is_active" :on="__('catalog.business_sector.status.active')"
                    :off="__('catalog.business_sector.status.inactive')"
                    wire:model="form.data.is_active" />
            </x-catalog.form-row>
        </x-catalog.form-shell>
    </x-slot:form>
</x-catalog.master>
