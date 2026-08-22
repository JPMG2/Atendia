<?php

use App\Interfaces\Catalog\DataTable;
use App\Livewire\Forms\Catalog\BaseCatalogForm;
use App\Livewire\Forms\Catalog\ProvinceForm;
use App\Models\Country;
use App\Models\Province;
use App\Traits\InteractsWithCatalogEditor;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Editor del maestro Provincias (tabla `provinces`).
 *
 * El chrome (toolbar, tabla, barra del form, pie de acciones) y todo el riel de
 * Alpine viven en `<x-catalog.*>` y en `catalogMaster()`: acá quedan SOLO las
 * acciones del server y los campos propios del maestro. Livewire 4 nativo (SFC).
 */
new class extends Component {
    use InteractsWithCatalogEditor;

    public ProvinceForm $form;

    protected function catalogForm(): BaseCatalogForm
    {
        return $this->form;
    }

    protected function catalogModel(): DataTable
    {
        return new Province;
    }

    /**
     * Opciones del combobox de país. Van TODOS, también los inactivos: si una
     * provincia ya apunta a un país dado de baja, filtrarlo acá haría que al
     * abrirla el combobox apareciera vacío y el guardado le cambiara el país sin
     * que nadie lo tocara.
     *
     * @return array<int, array{value: int, label: string}>
     */
    #[Computed]
    public function countryOptions(): array
    {
        return Country::query()
            ->orderBy('name')
            ->get()
            ->map(
                fn(Country $country): array => [
                    'value' => $country->id,
                    'label' => $country->code . ' — ' . $country->name,
                ],
            )
            ->all();
    }
};
?>

<x-catalog.master :rows="$initialRows"
    :search="['name', 'country']"
    :rules="[
        'name' => ['required', ['minLength', 3], ['maxLength', 255], 'noMarkup'],
        'country_id' => ['required'],
    ]">

    {{-- ============ VISTA TABLA (el "mostrar") ============ --}}
    <x-slot:list>
        <x-catalog.toolbar :search-placeholder="__('catalog.province.search_placeholder')"
            :search-label="__('catalog.province.search_label')" :singular="__('catalog.province.singular')"
            :plural="__('catalog.province.plural')" :create="__('catalog.province.create')" />

        <x-catalog.table :empty="__('catalog.province.empty')" :columns="[
            ['label' => __('catalog.province.columns.name'), 'class' => 'catalog-col-fill'],
            ['label' => __('catalog.province.columns.country')],
            ['label' => __('catalog.province.columns.status')],
        ]">
            <td class="catalog-cell-name catalog-cell-fill" x-text="row.name"></td>
            <td x-text="row.country"></td>
            <td>
                <span class="catalog-status" x-bind:class="row.active ? 'is-on' : 'is-off'">
                    <span class="dot"></span><span
                        x-text="row.active ? {{ \Illuminate\Support\Js::from(__('catalog.province.status.active')) }} : {{ \Illuminate\Support\Js::from(__('catalog.province.status.inactive')) }}"></span>
                </span>
            </td>
        </x-catalog.table>
    </x-slot:list>

    {{-- ============ VISTA FORMULARIO (crear / editar) ============ --}}
    <x-slot:form>
        <x-catalog.form-shell :new="__('catalog.province.new')" :new-title="__('catalog.province.new_title')"
            :edit-title="__('catalog.province.edit_title')" :create="__('catalog.province.create')">

            {{-- Tres campos: entran en una fila que llega al borde. El nombre se
                 lleva el sobrante; el estado cierra la línea. --}}
            <x-catalog.form-row>
                <x-inputsform.input span="text" :label="__('catalog.province.fields.name')" required name="name"
                    :placeholder="__('catalog.province.fields.name_placeholder')" alpine-error="name"
                    wire:model="form.data.name" />

                <x-inputsform.combobox span="text" :label="__('catalog.province.fields.country')" required
                    name="country_id" :placeholder="__('catalog.province.fields.country_placeholder')"
                    :options="$this->countryOptions" :value="$form->data?->country_id"
                    alpine-error="country_id" wire:model="form.data.country_id" />

                <x-inputsform.switch-field span="short" :label="__('catalog.province.fields.status')" name="is_active"
                    :on="__('catalog.province.status.active')" :off="__('catalog.province.status.inactive')"
                    wire:model="form.data.is_active" />
            </x-catalog.form-row>
        </x-catalog.form-shell>
    </x-slot:form>
</x-catalog.master>
