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
 * Editor del maestro Regiones (tabla `regions`).
 *
 * El chrome (toolbar, tabla, barra del form, pie de acciones) y todo el riel de
 * Alpine viven en `<x-catalog.*>` y en `catalogMaster()`: acá quedan SOLO las
 * acciones del server y los campos propios del maestro. Livewire 4 nativo (SFC).
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
     * Opciones del combobox de provincia. Van TODAS, también las inactivas: si una
     * región ya apunta a una provincia dada de baja, filtrarla acá haría que al
     * abrirla el combobox apareciera vacío y el guardado le cambiara la provincia
     * sin que nadie la tocara.
     *
     * La etiqueta lleva el país porque el nombre de provincia SE REPITE entre
     * países ("Córdoba" está en Argentina y en España): sin él, el combobox
     * muestra dos opciones idénticas y no hay forma de saber cuál es cuál.
     *
     * @return array<int, array{value: int, label: string}>
     */
    #[Computed]
    public function provinceOptions(): array
    {
        return Province::query()
            ->with('country:id,code')
            ->orderBy('name')
            ->get()
            ->map(
                fn(Province $province): array => [
                    'value' => $province->id,
                    'label' => $province->name . ' — ' . ($province->country?->code ?? '—'),
                ],
            )
            ->all();
    }
};
?>

<x-catalog.master :rows="$initialRows"
    :blank="['name' => '', 'province' => '', 'country' => '', 'active' => true]"
    :search="['name', 'province', 'country']"
    :rules="[
        'name' => ['required', ['minLength', 3], ['maxLength', 255], 'noMarkup'],
        'province_id' => ['required'],
    ]">

    {{-- ============ VISTA TABLA (el "mostrar") ============ --}}
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

    {{-- ============ VISTA FORMULARIO (crear / editar) ============ --}}
    <x-slot:form>
        <x-catalog.form-shell :new="__('catalog.region.new')" :new-title="__('catalog.region.new_title')"
            :edit-title="__('catalog.region.edit_title')" :create="__('catalog.region.create')">

            {{-- Tres campos: entran en una fila que llega al borde. El nombre se
                 lleva el sobrante; el estado cierra la línea. --}}
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
