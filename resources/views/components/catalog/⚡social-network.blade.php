<?php

use App\Interfaces\Catalog\DataTable;
use App\Livewire\Forms\Catalog\BaseCatalogForm;
use App\Livewire\Forms\Catalog\SocialNetworkForm;
use App\Models\SocialNetwork;
use App\Traits\InteractsWithCatalogEditor;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Editor del maestro Redes sociales (tabla `social_networks`).
 *
 * El chrome (toolbar, tabla, barra del form, pie de acciones) y todo el riel de
 * Alpine viven en `<x-catalog.*>` y en `catalogMaster()`: acá quedan SOLO las
 * acciones del server y los campos propios del maestro. Livewire 4 nativo (SFC).
 */
new class extends Component {
    use InteractsWithCatalogEditor;

    public SocialNetworkForm $form;

    protected function catalogForm(): BaseCatalogForm
    {
        return $this->form;
    }

    protected function catalogModel(): DataTable
    {
        return new SocialNetwork;
    }

    /**
     * Opciones del combobox de ícono: las CLAVES de config/icons.php, que es el
     * catálogo real de glifos del sistema. Texto libre no sirve — <x-icon> con un
     * nombre inexistente no dibuja nada y la red queda sin ícono sin avisar.
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
    :search="['name', 'abbreviation']"
    :rules="[
        'name' => ['required', ['minLength', 3], ['maxLength', 255], 'noMarkup'],
        'url' => ['required', ['maxLength', 255], 'noMarkup'],
        'abbreviation' => [['maxLength', 10], 'noMarkup'],
    ]">

    {{-- ============ VISTA TABLA (el "mostrar") ============ --}}
    <x-slot:list>
        <x-catalog.toolbar :search-placeholder="__('catalog.social_network.search_placeholder')"
            :search-label="__('catalog.social_network.search_label')" :singular="__('catalog.social_network.singular')"
            :plural="__('catalog.social_network.plural')" :create="__('catalog.social_network.create')" />

        <x-catalog.table :empty="__('catalog.social_network.empty')" :columns="[
            ['label' => __('catalog.social_network.columns.name'), 'class' => 'catalog-col-fill'],
            ['label' => __('catalog.social_network.columns.abbreviation')],
            ['label' => __('catalog.social_network.columns.url')],
            ['label' => __('catalog.social_network.columns.icon')],
            ['label' => __('catalog.social_network.columns.status')],
        ]">
            <td class="catalog-cell-name catalog-cell-fill" x-text="row.name"></td>
            <td><span class="catalog-code" x-text="row.abbreviation"></span></td>
            <td class="catalog-cell-sym" x-text="row.url"></td>
            <td class="catalog-cell-sym" x-text="row.icon"></td>
            <td>
                <span class="catalog-status" x-bind:class="row.active ? 'is-on' : 'is-off'">
                    <span class="dot"></span><span
                        x-text="row.active ? {{ \Illuminate\Support\Js::from(__('catalog.social_network.status.active')) }} : {{ \Illuminate\Support\Js::from(__('catalog.social_network.status.inactive')) }}"></span>
                </span>
            </td>
        </x-catalog.table>
    </x-slot:list>

    {{-- ============ VISTA FORMULARIO (crear / editar) ============ --}}
    <x-slot:form>
        <x-catalog.form-shell :new="__('catalog.social_network.new')" :new-title="__('catalog.social_network.new_title')"
            :edit-title="__('catalog.social_network.edit_title')" :create="__('catalog.social_network.create')">

            {{-- Fila 1: el identificador corto y el nombre, que se lleva todo el resto. --}}
            <x-catalog.form-row>
                <x-inputsform.input span="code" :label="__('catalog.social_network.fields.abbreviation')"
                    name="abbreviation" :hint="__('catalog.social_network.fields.abbreviation_hint')" maxlength="10"
                    alpine-error="abbreviation" wire:model="form.data.abbreviation" />

                <x-inputsform.input span="text" :label="__('catalog.social_network.fields.name')" required name="name"
                    :placeholder="__('catalog.social_network.fields.name_placeholder')" alpine-error="name"
                    wire:model="form.data.name" />
            </x-catalog.form-row>

            {{-- Fila 2: el resto de los campos repartiéndose el ancho completo. --}}
            <x-catalog.form-row>
                <x-inputsform.input span="long" :label="__('catalog.social_network.fields.url')" required name="url"
                    type="url" :hint="__('catalog.social_network.fields.url_hint')" maxlength="255" alpine-error="url"
                    wire:model="form.data.url" />

                <x-inputsform.combobox span="text" :label="__('catalog.social_network.fields.icon')" name="icon"
                    :placeholder="__('catalog.social_network.fields.icon_placeholder')"
                    :hint="__('catalog.social_network.fields.icon_hint')" :options="$this->iconOptions"
                    :value="$form->data?->icon" alpine-error="icon"
                    wire:model="form.data.icon" />

                <x-inputsform.switch-field span="text" :label="__('catalog.social_network.fields.status')"
                    name="is_active" :on="__('catalog.social_network.status.active')"
                    :off="__('catalog.social_network.status.inactive')"
                    wire:model="form.data.is_active" />
            </x-catalog.form-row>

        </x-catalog.form-shell>
    </x-slot:form>
</x-catalog.master>
