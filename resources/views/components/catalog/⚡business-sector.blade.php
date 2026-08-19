<?php

use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\Catalog\BusinessSectorForm;
use App\Models\BusinessSector;
use App\Traits\HasNotifications;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Editor del maestro Rubros (tabla `business_sectors`).
 *
 * El chrome (toolbar, tabla, barra del form, pie de acciones) y todo el riel de
 * Alpine viven en `<x-catalog.*>` y en `catalogMaster()`: acá quedan SOLO las
 * acciones del server y los campos propios del maestro. Livewire 4 nativo (SFC).
 */
new class extends Component {
    use HasNotifications;

    public BusinessSectorForm $form;

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

        $this->initialRows = $this->businessSectors->all();
    }

    /**
     * Devuelve si se guardó, para que Alpine sepa si volver a la lista o dejar
     * al usuario en el formulario con lo que escribió.
     */
    public function create(): bool
    {
        $notification = $this->form->storeBusinessSector();

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
        $notification = $this->form->updateBusinessSector();

        $this->dispatchNotification($notification);

        if ($notification->type !== NotificationType::Success) {
            return false;
        }

        $this->resetForm();

        $this->reloadTable();

        return true;
    }

    /**
     * Devuelve si se pudo abrir. Si el rubro ya no existe avisa y el front se
     * queda en la lista, en vez de mostrar un formulario vacío o un 404 crudo.
     */
    public function openEdit(int $businessSectorId): bool
    {
        if (!$this->form->loadBusinessSectorData($businessSectorId)) {
            $this->dispatchNotification(new NotificationDto(__('notifications.not_found'), NotificationType::Error));

            return false;
        }

        return true;
    }

    /**
     * Un alta arranca en blanco: vaciar el estado de Alpine no alcanza, el form
     * del server sigue con el rubro que se abrió antes.
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
        unset($this->businessSectors);

        $this->dispatch('catalog-rows-refreshed', rows: $this->businessSectors);
    }

    /**
     * Rubros para el riel de Alpine. Se entregan una sola vez al montar: el
     * buscador y el contador filtran client-side, sin request al server.
     *
     * Se ordenan por `sort_order` y no por nombre: el orden es justamente lo que
     * el admin decide acá para que el negocio lo vea así al elegir.
     *
     * @return \Illuminate\Support\Collection<int, array{id: int, code: string, name: string, description: string, order: int, active: bool}>
     */
    #[Computed]
    public function businessSectors(): \Illuminate\Support\Collection
    {
        return BusinessSector::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(
                fn(BusinessSector $sector): array => [
                    'id' => $sector->id,
                    'code' => $sector->code,
                    'name' => $sector->name,
                    'description' => $sector->description ?? '',
                    'order' => $sector->sort_order,
                    'active' => $sector->is_active,
                ],
            )
            ->values();
    }
};
?>

<x-catalog.master :rows="$initialRows" path="form.businessSectorData"
    :blank="['code' => '', 'name' => '', 'description' => '', 'order' => 0, 'active' => true]"
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
                    wire:model="form.businessSectorData.code" />

                <x-inputsform.input span="text" :label="__('catalog.business_sector.fields.name')" required name="name"
                    :placeholder="__('catalog.business_sector.fields.name_placeholder')" alpine-error="name"
                    wire:model="form.businessSectorData.name" />
            </x-catalog.form-row>

            {{-- Fila 2: la descripción absorbe el sobrante y el estado cierra la
                 línea, para no gastar una fila entera en un booleano. --}}
            <x-catalog.form-row>
                <x-inputsform.input span="long" :label="__('catalog.business_sector.fields.description')"
                    name="description" :placeholder="__('catalog.business_sector.fields.description_placeholder')"
                    :hint="__('catalog.business_sector.fields.description_hint')" maxlength="255"
                    alpine-error="description" wire:model="form.businessSectorData.description" />

                <x-inputsform.input span="code" :label="__('catalog.business_sector.fields.order')" name="sort_order"
                    type="number" min="0" max="32767" :hint="__('catalog.business_sector.fields.order_hint')"
                    alpine-error="sort_order" wire:model="form.businessSectorData.sort_order" />

                <x-inputsform.switch-field span="short" :label="__('catalog.business_sector.fields.status')"
                    name="is_active" :on="__('catalog.business_sector.status.active')"
                    :off="__('catalog.business_sector.status.inactive')"
                    wire:model="form.businessSectorData.is_active" />
            </x-catalog.form-row>
        </x-catalog.form-shell>
    </x-slot:form>
</x-catalog.master>
