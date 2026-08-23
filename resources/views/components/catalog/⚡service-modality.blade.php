<?php

use App\Interfaces\Catalog\DataTable;
use App\Livewire\Forms\Catalog\BaseCatalogForm;
use App\Livewire\Forms\Catalog\ServiceModalityForm;
use App\Models\ServiceModality;
use App\Traits\InteractsWithCatalogEditor;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Editor del maestro Modalidades (tabla `service_modalities`).
 *
 * El chrome (toolbar, tabla, barra del form, pie de acciones) y todo el riel de
 * Alpine viven en `<x-catalog.*>` y en `catalogMaster()`: acá quedan SOLO las
 * acciones del server y los campos propios del maestro. Livewire 4 nativo (SFC).
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
     * Opciones del combobox de ícono: las CLAVES de config/icons.php, que es el
     * catálogo real de glifos del sistema. Texto libre no sirve — <x-icon> con un
     * nombre inexistente pinta un hueco.
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

    {{-- ============ VISTA TABLA (el "mostrar") ============ --}}
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
            {{-- El glifo REAL al lado del nombre, en vez de una columna con su
                 clave: "calendar-check" es config, no un dato del catálogo. Y
                 debajo, qué pide y qué recuerda esa modalidad. --}}
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

    {{-- ============ VISTA FORMULARIO (crear / editar) ============ --}}
    <x-slot:form>
        <x-catalog.form-shell :new="__('catalog.service_modality.new')"
            :new-title="__('catalog.service_modality.new_title')"
            :edit-title="__('catalog.service_modality.edit_title')"
            :create="__('catalog.service_modality.create')" title-key="name">

            {{-- Fila 1: la clave corta y el nombre, que se lleva todo el resto. --}}
            <x-catalog.form-row>
                <x-inputsform.input span="code" :label="__('catalog.service_modality.fields.code')" required name="code"
                    :hint="__('catalog.service_modality.fields.code_hint')" maxlength="30" alpine-error="code"
                    wire:model="form.data.code" />

                <x-inputsform.input span="text" :label="__('catalog.service_modality.fields.name')" required name="name"
                    :placeholder="__('catalog.service_modality.fields.name_placeholder')" alpine-error="name"
                    wire:model="form.data.name" />
            </x-catalog.form-row>

            {{-- Fila 2: la descripción absorbe el sobrante y el estado cierra la
                 línea, para no gastar una fila entera en un booleano. --}}
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
