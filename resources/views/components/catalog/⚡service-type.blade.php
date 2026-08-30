<?php

use App\Interfaces\Catalog\DataTable;
use App\Livewire\Forms\Catalog\BaseCatalogForm;
use App\Livewire\Forms\Catalog\ServiceTypeForm;
use App\Models\BusinessSector;
use App\Models\ServiceModality;
use App\Models\ServiceType;
use App\Traits\InteractsWithCatalogEditor;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Editor del maestro Tipos de servicio (tabla `service_types`).
 *
 * El chrome (toolbar, tabla, barra del form, pie de acciones) y todo el riel de
 * Alpine viven en `<x-catalog.*>` y en `catalogMaster()`: acá quedan SOLO las
 * acciones del server y los campos propios del maestro. Livewire 4 nativo (SFC).
 *
 * La columna "Atributos" de la tabla es de LECTURA: asignarlos es una pantalla
 * aparte (una fila por atributo con su obligatorio, su orden y su etiqueta
 * propia), no un campo de este formulario.
 */
new class extends Component {
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
     * Opciones del combobox de modalidad. Van TODAS, también las inactivas: si un
     * tipo ya apunta a una modalidad dada de baja, filtrarla acá haría que al
     * abrirlo el combobox apareciera vacío y el guardado le cambiara la modalidad
     * sin que nadie la tocara.
     *
     * @return array<int, array{value: int, label: string}>
     */
    #[Computed]
    public function modalityOptions(): array
    {
        return ServiceModality::options();
    }

    /**
     * Opciones del combobox de rubro. Mismo criterio que arriba.
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

<x-catalog.master :rows="$initialRows"
    :search="['code', 'name', 'modality', 'sector', 'attributes']"
    :rules="[
        'code' => ['required', ['minLength', 3], ['maxLength', 40], 'noMarkup'],
        'name' => ['required', ['minLength', 3], ['maxLength', 255], 'noMarkup'],
        'service_modality_id' => ['required'],
        'sort_order' => ['integer', ['min', 0], ['max', 32767]],
    ]">

    {{-- Table view: the list. --}}
    <x-slot:list>
        <x-catalog.toolbar :search-placeholder="__('catalog.service_type.search_placeholder')"
            :search-label="__('catalog.service_type.search_label')" :singular="__('catalog.service_type.singular')"
            :plural="__('catalog.service_type.plural')" :create="__('catalog.service_type.create')" />

        <x-catalog.table :empty="__('catalog.service_type.empty')" :columns="[
            ['label' => __('catalog.service_type.columns.code')],
            ['label' => __('catalog.service_type.columns.name'), 'class' => 'catalog-col-fill'],
            ['label' => __('catalog.service_type.columns.modality')],
            ['label' => __('catalog.service_type.columns.attributes')],
            ['label' => __('catalog.service_type.columns.status')],
        ]">
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
                    <span class="dot"></span><span
                        x-text="row.active ? {{ \Illuminate\Support\Js::from(__('catalog.service_type.status.active')) }} : {{ \Illuminate\Support\Js::from(__('catalog.service_type.status.inactive')) }}"></span>
                </span>
            </td>
        </x-catalog.table>
    </x-slot:list>

    {{-- Form view: create and edit. --}}
    <x-slot:form>
        <x-catalog.form-shell :new="__('catalog.service_type.new')" :new-title="__('catalog.service_type.new_title')"
            :edit-title="__('catalog.service_type.edit_title')" :create="__('catalog.service_type.create')"
            title-key="name">

            {{-- Row 1: the short key, the name and how it is offered. --}}
            <x-catalog.form-row>
                <x-inputsform.input span="code" :label="__('catalog.service_type.fields.code')" required name="code"
                    :hint="__('catalog.service_type.fields.code_hint')" maxlength="40" alpine-error="code"
                    wire:model="form.data.code" />

                <x-inputsform.input span="text" :label="__('catalog.service_type.fields.name')" required name="name"
                    :placeholder="__('catalog.service_type.fields.name_placeholder')" alpine-error="name"
                    wire:model="form.data.name" />

                <x-inputsform.combobox span="text" :label="__('catalog.service_type.fields.modality')" required
                    name="service_modality_id" :placeholder="__('catalog.service_type.fields.modality_placeholder')"
                    :hint="__('catalog.service_type.fields.modality_hint')" :options="$this->modalityOptions"
                    :value="$form->data?->service_modality_id" alpine-error="service_modality_id"
                    wire:model="form.data.service_modality_id" />
            </x-catalog.form-row>

            {{-- Row 2: the description takes the slack and the status closes the
            line, so a boolean does not cost a whole row. --}}
            <x-catalog.form-row>
                <x-inputsform.input span="long" :label="__('catalog.service_type.fields.description')"
                    name="description" :placeholder="__('catalog.service_type.fields.description_placeholder')"
                    :hint="__('catalog.service_type.fields.description_hint')" maxlength="255"
                    alpine-error="description" wire:model="form.data.description" />

                <x-inputsform.combobox span="text" :label="__('catalog.service_type.fields.sector')"
                    name="business_sector_id" :placeholder="__('catalog.service_type.fields.sector_placeholder')"
                    :hint="__('catalog.service_type.fields.sector_hint')" :options="$this->sectorOptions"
                    :value="$form->data?->business_sector_id" alpine-error="business_sector_id"
                    wire:model="form.data.business_sector_id" />

                <x-inputsform.input span="code" :label="__('catalog.service_type.fields.order')" name="sort_order"
                    type="number" min="0" max="32767" :hint="__('catalog.service_type.fields.order_hint')"
                    alpine-error="sort_order" wire:model="form.data.sort_order" />

                <x-inputsform.switch-field span="short" :label="__('catalog.service_type.fields.status')"
                    name="is_active" :on="__('catalog.service_type.status.active')"
                    :off="__('catalog.service_type.status.inactive')" wire:model="form.data.is_active" />
            </x-catalog.form-row>
        </x-catalog.form-shell>
    </x-slot:form>
</x-catalog.master>
