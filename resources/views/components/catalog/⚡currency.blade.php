<?php

use App\Interfaces\Catalog\DataTable;
use App\Livewire\Forms\Catalog\BaseCatalogForm;
use App\Livewire\Forms\Catalog\CurrencyForm;
use App\Models\Currency;
use App\Traits\InteractsWithCatalogEditor;
use Livewire\Component;

/**
 * Editor del maestro Monedas (tabla `currencies`).
 *
 * El chrome (toolbar, tabla, barra del form, pie de acciones) y todo el riel de
 * Alpine viven en `<x-catalog.*>` y en `catalogMaster()`: acá quedan SOLO las
 * acciones del server y los campos propios del maestro. Livewire 4 nativo (SFC).
 */
new class extends Component {
    use InteractsWithCatalogEditor;

    public CurrencyForm $form;

    protected function catalogForm(): BaseCatalogForm
    {
        return $this->form;
    }

    protected function catalogModel(): DataTable
    {
        return new Currency;
    }
};
?>

<x-catalog.master :rows="$initialRows"
    :blank="['code' => '', 'name' => '', 'symbol' => '', 'decimals' => 2, 'active' => true]"
    :search="['code', 'name']"
    :rules="[
        'code' => ['required', 'alpha', ['length', 3]],
        'name' => ['required', ['minLength', 3], ['maxLength', 255], 'noMarkup'],
        'symbol' => ['required', ['minLength', 1], ['maxLength', 5], 'noMarkup'],
        'decimal_places' => ['required', 'integer', ['min', 0], ['max', 2]],
    ]">

    {{-- ============ VISTA TABLA (el "mostrar") ============ --}}
    <x-slot:list>
        <x-catalog.toolbar :search-placeholder="__('catalog.currency.search_placeholder')"
            :search-label="__('catalog.currency.search_label')" :singular="__('catalog.currency.singular')"
            :plural="__('catalog.currency.plural')" :create="__('catalog.currency.create')" />

        <x-catalog.table :empty="__('catalog.currency.empty')" :columns="[
            ['label' => __('catalog.currency.columns.code')],
            ['label' => __('catalog.currency.columns.name'), 'class' => 'catalog-col-fill'],
            ['label' => __('catalog.currency.columns.symbol')],
            ['label' => __('catalog.currency.columns.decimals'), 'class' => 'is-num'],
            ['label' => __('catalog.currency.columns.status')],
        ]">
            <td><span class="catalog-code" x-text="row.code"></span></td>
            <td class="catalog-cell-name catalog-cell-fill" x-text="row.name"></td>
            <td class="catalog-cell-sym" x-text="row.symbol"></td>
            <td class="catalog-cell-num" x-text="row.decimals"></td>
            <td>
                <span class="catalog-status" x-bind:class="row.active ? 'is-on' : 'is-off'">
                    <span class="dot"></span><span
                        x-text="row.active ? {{ \Illuminate\Support\Js::from(__('catalog.currency.status.active')) }} : {{ \Illuminate\Support\Js::from(__('catalog.currency.status.inactive')) }}"></span>
                </span>
            </td>
        </x-catalog.table>
    </x-slot:list>

    {{-- ============ VISTA FORMULARIO (crear / editar) ============ --}}
    <x-slot:form>
        <x-catalog.form-shell :new="__('catalog.currency.new')" :new-title="__('catalog.currency.new_title')"
            :edit-title="__('catalog.currency.edit_title')" :create="__('catalog.currency.create')" title-key="code">

            {{-- Fila 1: el identificador corto y el nombre, que se lleva todo el resto. --}}
            <x-catalog.form-row>
                <x-inputsform.input span="code" :label="__('catalog.currency.fields.code')" required name="code"
                    :hint="__('catalog.currency.fields.code_hint')" maxlength="3" alpine-error="code" x-mask="aaa"
                    style="text-transform:uppercase" wire:model="form.data.code" />

                <x-inputsform.input span="text" :label="__('catalog.currency.fields.name')" required name="name"
                    :placeholder="__('catalog.currency.fields.name_placeholder')" alpine-error="name"
                    wire:model="form.data.name" />
            </x-catalog.form-row>

            {{-- Fila 2: el resto de los campos repartiéndose el ancho completo. --}}
            <x-catalog.form-row>
                <x-inputsform.input span="text" :label="__('catalog.currency.fields.symbol')" required name="symbol"
                    :hint="__('catalog.currency.fields.symbol_hint')" maxlength="5" alpine-error="symbol"
                    wire:model="form.data.symbol" />

                <x-inputsform.input span="text" :label="__('catalog.currency.fields.decimals')" name="decimal_places"
                    type="number" min="0" max="2" alpine-error="decimal_places"
                    wire:model="form.data.decimal_places" />

                <x-inputsform.switch-field span="text" :label="__('catalog.currency.fields.status')" name="is_active"
                    :on="__('catalog.currency.status.active')" :off="__('catalog.currency.status.inactive')"
                    wire:model="form.data.is_active" />
            </x-catalog.form-row>

        </x-catalog.form-shell>
    </x-slot:form>
</x-catalog.master>
