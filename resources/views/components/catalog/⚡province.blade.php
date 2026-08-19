<?php

use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\Catalog\ProvinceForm;
use App\Models\Country;
use App\Models\Province;
use App\Traits\HasNotifications;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Editor del maestro Provincias (tabla `provinces`).
 *
 * El chrome (toolbar, tabla, barra del form, pie de acciones) y todo el riel de
 * Alpine viven en `<x-catalog.*>` y en `catalogMaster()`: acá quedan SOLO las
 * acciones del server y los campos propios del maestro. Livewire 4 nativo (SFC).
 */
new class extends Component {
    use HasNotifications;

    public ProvinceForm $form;

    /**
     * Semilla del riel de Alpine, CONGELADA al montar. Ver el comentario de
     * `<x-catalog.master>`: si cambiara, Alpine re-inicializaría el editor.
     *
     * @var array<int, array<string, mixed>>
     */
    #[Locked]
    public array $initialRows = [];

    public function mount(): void
    {
        $this->form->setup();

        $this->initialRows = $this->provinces->all();
    }

    /**
     * Devuelve si se guardó, para que Alpine sepa si volver a la lista o dejar
     * al usuario en el formulario con lo que escribió.
     */
    public function create(): bool
    {
        $notification = $this->form->storeProvince();

        $this->dispatchNotification($notification);

        if ($notification->type !== NotificationType::Success) {
            return false;
        }

        $this->resetForm();

        $this->reloadTable();

        return true;
    }

    public function update(): bool
    {
        $notification = $this->form->updateProvince();

        $this->dispatchNotification($notification);

        if ($notification->type !== NotificationType::Success) {
            return false;
        }

        $this->resetForm();

        $this->reloadTable();

        return true;
    }

    /**
     * Devuelve si se pudo abrir. Si la provincia ya no existe avisa y el front se
     * queda en la lista, en vez de mostrar un formulario vacío o un 404 crudo.
     */
    public function openEdit(int $provinceId): bool
    {
        if (!$this->form->loadProvinceData($provinceId)) {
            $this->dispatchNotification(new NotificationDto(__('notifications.not_found'), NotificationType::Error));

            return false;
        }

        return true;
    }

    /**
     * Un alta arranca en blanco: vaciar el estado de Alpine no alcanza, el form
     * del server sigue con la provincia que se abrió antes.
     */
    public function openCreate(): void
    {
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->form->reset();

        $this->form->setup();
    }

    protected function reloadTable(): void
    {
        unset($this->provinces);

        $this->dispatch('catalog-rows-refreshed', rows: $this->provinces);
    }

    /**
     * Provincias para el riel de Alpine. Se entregan una sola vez al montar: el
     * buscador y el contador filtran client-side, sin request al server.
     *
     * El `id` viaja siempre: es la única clave estable para editar. El `name` es
     * editable por el usuario, así que no sirve para identificar la fila.
     *
     * @return \Illuminate\Support\Collection<int, array{id: int, name: string, country: string, active: bool}>
     */
    #[Computed]
    public function provinces(): \Illuminate\Support\Collection
    {
        return Province::query()
            ->with('country:id,name')
            ->orderBy('name')
            ->get()
            ->map(
                fn(Province $province): array => [
                    'id' => $province->id,
                    'name' => $province->name,
                    'country' => $province->country?->name ?? '',
                    'active' => $province->is_active,
                ],
            )
            ->values();
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

<x-catalog.master :rows="$initialRows" path="form.provinceData"
    :blank="['name' => '', 'country' => '', 'active' => true]"
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
            ['label' => __('catalog.province.columns.name'), 'class' => 'catalog-col-name'],
            ['label' => __('catalog.province.columns.country')],
            ['label' => __('catalog.province.columns.status')],
        ]">
            <td class="catalog-cell-name" x-text="row.name"></td>
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
                    wire:model="form.provinceData.name" />

                <x-inputsform.combobox span="text" :label="__('catalog.province.fields.country')" required
                    name="country_id" :placeholder="__('catalog.province.fields.country_placeholder')"
                    :options="$this->countryOptions" :value="$form->provinceData?->country_id"
                    alpine-error="country_id" wire:model="form.provinceData.country_id" />

                <x-inputsform.switch-field span="short" :label="__('catalog.province.fields.status')" name="is_active"
                    :on="__('catalog.province.status.active')" :off="__('catalog.province.status.inactive')"
                    wire:model="form.provinceData.is_active" />
            </x-catalog.form-row>
        </x-catalog.form-shell>
    </x-slot:form>
</x-catalog.master>
