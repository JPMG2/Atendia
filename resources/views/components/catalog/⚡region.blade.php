<?php

use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\Catalog\RegionForm;
use App\Models\Province;
use App\Models\Region;
use App\Traits\HasNotifications;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Editor del maestro Regiones (tabla `regions`).
 *
 * El chrome (toolbar, tabla, barra del form, pie de acciones) y todo el riel de
 * Alpine viven en `<x-catalog.*>` y en `catalogMaster()`: acá quedan SOLO las
 * acciones del server y los campos propios del maestro. Livewire 4 nativo (SFC).
 */
new class extends Component {
    use HasNotifications;

    public RegionForm $form;

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

        $this->initialRows = $this->regions->all();
    }

    /**
     * Devuelve si se guardó, para que Alpine sepa si volver a la lista o dejar
     * al usuario en el formulario con lo que escribió.
     */
    public function create(): bool
    {
        $notification = $this->form->storeRegion();

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
        $notification = $this->form->updateRegion();

        $this->dispatchNotification($notification);

        if ($notification->type !== NotificationType::Success) {
            return false;
        }

        $this->resetForm();

        $this->reloadTable();

        return true;
    }

    /**
     * Devuelve si se pudo abrir. Si la región ya no existe avisa y el front se
     * queda en la lista, en vez de mostrar un formulario vacío o un 404 crudo.
     */
    public function openEdit(int $regionId): bool
    {
        if (!$this->form->loadRegionData($regionId)) {
            $this->dispatchNotification(new NotificationDto(__('notifications.not_found'), NotificationType::Error));

            return false;
        }

        return true;
    }

    /**
     * Un alta arranca en blanco: vaciar el estado de Alpine no alcanza, el form
     * del server sigue con la región que se abrió antes.
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
        unset($this->regions);

        $this->dispatch('catalog-rows-refreshed', rows: $this->regions);
    }

    /**
     * Regiones para el riel de Alpine. Se entregan una sola vez al montar: el
     * buscador y el contador filtran client-side, sin request al server.
     *
     * El `id` viaja siempre: es la única clave estable para editar. El `name` es
     * editable por el usuario, así que no sirve para identificar la fila.
     *
     * La región cuelga de una provincia y la provincia de un país. El país viaja
     * en la fila —y no solo la provincia— porque si no hay que saberse de memoria
     * a qué país pertenece cada provincia para entender la lista.
     *
     * `country_id` va en el select de la provincia a propósito: sin esa columna
     * Eloquent no puede resolver el `belongsTo` al país y `country` volvería vacío.
     *
     * @return \Illuminate\Support\Collection<int, array{id: int, name: string, province: string, country: string, active: bool}>
     */
    #[Computed]
    public function regions(): \Illuminate\Support\Collection
    {
        return Region::query()
            ->with(['province:id,name,country_id', 'province.country:id,name'])
            ->orderBy('name')
            ->get()
            ->map(
                fn(Region $region): array => [
                    'id' => $region->id,
                    'name' => $region->name,
                    'province' => $region->province?->name ?? '',
                    'country' => $region->province?->country?->name ?? '',
                    'active' => $region->is_active,
                ],
            )
            ->values();
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

<x-catalog.master :rows="$initialRows" path="form.regionData"
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
            ['label' => __('catalog.region.columns.name'), 'class' => 'catalog-col-name'],
            ['label' => __('catalog.region.columns.province')],
            ['label' => __('catalog.region.columns.country')],
            ['label' => __('catalog.region.columns.status')],
        ]">
            <td class="catalog-cell-name" x-text="row.name"></td>
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
                    wire:model="form.regionData.name" />

                <x-inputsform.combobox span="text" :label="__('catalog.region.fields.province')" required
                    name="province_id" :placeholder="__('catalog.region.fields.province_placeholder')"
                    :options="$this->provinceOptions" :value="$form->regionData?->province_id"
                    alpine-error="province_id" wire:model="form.regionData.province_id" />

                <x-inputsform.switch-field span="short" :label="__('catalog.region.fields.status')" name="is_active"
                    :on="__('catalog.region.status.active')" :off="__('catalog.region.status.inactive')"
                    wire:model="form.regionData.is_active" />
            </x-catalog.form-row>
        </x-catalog.form-shell>
    </x-slot:form>
</x-catalog.master>
