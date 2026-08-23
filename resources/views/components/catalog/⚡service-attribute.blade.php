<?php

use App\Interfaces\Catalog\DataTable;
use App\Livewire\Forms\Catalog\BaseCatalogForm;
use App\Livewire\Forms\Catalog\ServiceAttributeForm;
use App\Models\ServiceAttribute;
use App\Traits\InteractsWithCatalogEditor;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Editor del maestro Atributos (tabla `service_attributes`).
 *
 * El chrome (toolbar, tabla, barra del form, pie de acciones) y todo el riel de
 * Alpine viven en `<x-catalog.*>` y en `catalogMaster()`: acá quedan SOLO las
 * acciones del server y los campos propios del maestro. Livewire 4 nativo (SFC).
 */
new class extends Component {
    use InteractsWithCatalogEditor;

    public ServiceAttributeForm $form;

    protected function catalogForm(): BaseCatalogForm
    {
        return $this->form;
    }

    protected function catalogModel(): DataTable
    {
        return new ServiceAttribute;
    }

    /**
     * Opciones del combobox de tipo de dato. Salen de config/attribute_types.php,
     * que es lo único que el sistema sabe pintar y validar.
     *
     * @return array<int, array{value: string, label: string}>
     */
    #[Computed]
    public function dataTypeOptions(): array
    {
        return collect(ServiceAttribute::dataTypes())
            ->map(fn(string $label, string $value): array => ['value' => $value, 'label' => $label])
            ->values()
            ->all();
    }
};
?>

<x-catalog.master :rows="$initialRows"
    :search="['code', 'name', 'description', 'type']"
    :rules="[
        'code' => ['required', ['minLength', 3], ['maxLength', 40], 'noMarkup'],
        'name' => ['required', ['minLength', 2], ['maxLength', 255], 'noMarkup'],
        'data_type' => ['required'],
        'sort_order' => ['integer', ['min', 0], ['max', 32767]],
    ]">

    {{-- ============ VISTA TABLA (el "mostrar") ============ --}}
    <x-slot:list>
        <x-catalog.toolbar :search-placeholder="__('catalog.service_attribute.search_placeholder')"
            :search-label="__('catalog.service_attribute.search_label')"
            :singular="__('catalog.service_attribute.singular')" :plural="__('catalog.service_attribute.plural')"
            :create="__('catalog.service_attribute.create')" />

        <x-catalog.table :empty="__('catalog.service_attribute.empty')" :columns="[
            ['label' => __('catalog.service_attribute.columns.code')],
            ['label' => __('catalog.service_attribute.columns.name'), 'class' => 'catalog-col-fill'],
            ['label' => __('catalog.service_attribute.columns.type')],
            ['label' => __('catalog.service_attribute.columns.options')],
            ['label' => __('catalog.service_attribute.columns.status')],
        ]">
            <td><span class="catalog-code" x-text="row.code"></span></td>
            {{-- La descripción va bajo el nombre, no en una columna propia: es una
                 aclaración, y como columna se comía el ancho de la tabla. --}}
            <td class="catalog-cell-fill">
                <span class="catalog-cell-primary">
                    <span class="name" x-text="row.name"></span>
                    <span class="sub" x-text="row.description"></span>
                </span>
            </td>
            {{-- Tipo, unidad y cardinalidad juntos: tres columnas para tres datos
                 chicos empujaban la tabla fuera del panel. --}}
            <td class="catalog-cell-meta" x-text="row.type"></td>
            {{-- Las opciones son una LISTA: como pastillas se cuentan de un
                 vistazo y envuelven solas, pegadas con comas son un párrafo. --}}
            <td>
                <span class="catalog-chips" x-show="row.options.length">
                    <template x-for="option in row.options" :key="option">
                        <span class="catalog-chip" x-text="option"></span>
                    </template>
                </span>
            </td>
            <td>
                <span class="catalog-status" x-bind:class="row.active ? 'is-on' : 'is-off'">
                    <span class="dot"></span><span
                        x-text="row.active ? {{ \Illuminate\Support\Js::from(__('catalog.service_attribute.status.active')) }} : {{ \Illuminate\Support\Js::from(__('catalog.service_attribute.status.inactive')) }}"></span>
                </span>
            </td>
        </x-catalog.table>
    </x-slot:list>

    {{-- ============ VISTA FORMULARIO (crear / editar) ============ --}}
    <x-slot:form>
        <x-catalog.form-shell :new="__('catalog.service_attribute.new')"
            :new-title="__('catalog.service_attribute.new_title')"
            :edit-title="__('catalog.service_attribute.edit_title')"
            :create="__('catalog.service_attribute.create')" title-key="name">

            {{-- Fila 1: la clave corta, el nombre y de qué clase es el dato. --}}
            <x-catalog.form-row>
                <x-inputsform.input span="code" :label="__('catalog.service_attribute.fields.code')" required name="code"
                    :hint="__('catalog.service_attribute.fields.code_hint')" maxlength="40" alpine-error="code"
                    wire:model="form.data.code" />

                <x-inputsform.input span="text" :label="__('catalog.service_attribute.fields.name')" required name="name"
                    :placeholder="__('catalog.service_attribute.fields.name_placeholder')" alpine-error="name"
                    wire:model="form.data.name" />

                <x-inputsform.combobox span="text" :label="__('catalog.service_attribute.fields.data_type')" required
                    name="data_type" :placeholder="__('catalog.service_attribute.fields.data_type_placeholder')"
                    :hint="__('catalog.service_attribute.fields.data_type_hint')" :options="$this->dataTypeOptions"
                    :value="$form->data?->data_type" alpine-error="data_type" wire:model="form.data.data_type" />
            </x-catalog.form-row>

            {{-- Fila 2: la descripción absorbe el sobrante y el estado cierra la
                 línea, para no gastar una fila entera en un booleano. --}}
            <x-catalog.form-row>
                <x-inputsform.input span="long" :label="__('catalog.service_attribute.fields.description')"
                    name="description" :placeholder="__('catalog.service_attribute.fields.description_placeholder')"
                    :hint="__('catalog.service_attribute.fields.description_hint')" maxlength="255"
                    alpine-error="description" wire:model="form.data.description" />

                <x-inputsform.input span="code" :label="__('catalog.service_attribute.fields.unit')" name="unit"
                    :placeholder="__('catalog.service_attribute.fields.unit_placeholder')"
                    :hint="__('catalog.service_attribute.fields.unit_hint')" maxlength="15" alpine-error="unit"
                    wire:model="form.data.unit" />

                <x-inputsform.input span="code" :label="__('catalog.service_attribute.fields.order')" name="sort_order"
                    type="number" min="0" max="32767" :hint="__('catalog.service_attribute.fields.order_hint')"
                    alpine-error="sort_order" wire:model="form.data.sort_order" />

                <x-inputsform.switch-field span="short" :label="__('catalog.service_attribute.fields.multiple')"
                    name="is_multiple" :on="__('catalog.service_attribute.multiple.on')"
                    :off="__('catalog.service_attribute.multiple.off')" wire:model="form.data.is_multiple" />

                <x-inputsform.switch-field span="short" :label="__('catalog.service_attribute.fields.status')"
                    name="is_active" :on="__('catalog.service_attribute.status.active')"
                    :off="__('catalog.service_attribute.status.inactive')" wire:model="form.data.is_active" />
            </x-catalog.form-row>

            {{-- Fila 3: las opciones de la lista. Va sola porque es la única que
                 puede llegar a ser larga, y solo la usa el tipo "Lista". --}}
            <x-catalog.form-row>
                <x-inputsform.input span="full" :label="__('catalog.service_attribute.fields.options')" name="options"
                    :placeholder="__('catalog.service_attribute.fields.options_placeholder')"
                    :hint="__('catalog.service_attribute.fields.options_hint')" maxlength="500" alpine-error="options"
                    wire:model="form.data.options" />
            </x-catalog.form-row>
        </x-catalog.form-shell>
    </x-slot:form>
</x-catalog.master>
