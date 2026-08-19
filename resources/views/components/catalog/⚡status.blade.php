<?php

use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\Catalog\CurrentStatusForm;
use App\Models\CurrentStatus;
use App\Traits\HasNotifications;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Editor del maestro Estados (tabla `current_statuses`).
 *
 * El más chico de los maestros: la tabla tiene UN dato, el nombre. No lleva
 * `is_active` —la columna no existe— así que este editor no tiene switch de
 * estado ni columna Estado; agregarlos sería inventar un campo que la base no
 * guarda.
 *
 * El chrome y el riel de Alpine viven en `<x-catalog.*>` y en `catalogMaster()`.
 * Livewire 4 nativo (SFC).
 */
new class extends Component {
    use HasNotifications;

    public CurrentStatusForm $form;

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

        $this->initialRows = $this->statuses->all();
    }

    /**
     * Devuelve si se guardó, para que Alpine sepa si volver a la lista o dejar
     * al usuario en el formulario con lo que escribió.
     */
    public function create(): bool
    {
        $notification = $this->form->storeCurrentStatus();

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
        $notification = $this->form->updateCurrentStatus();

        $this->dispatchNotification($notification);

        if ($notification->type !== NotificationType::Success) {
            return false;
        }

        $this->resetForm();

        $this->reloadTable();

        return true;
    }

    /**
     * Devuelve si se pudo abrir. Si el estado ya no existe avisa y el front se
     * queda en la lista, en vez de mostrar un formulario vacío o un 404 crudo.
     */
    public function openEdit(int $currentStatusId): bool
    {
        if (!$this->form->loadCurrentStatusData($currentStatusId)) {
            $this->dispatchNotification(new NotificationDto(__('notifications.not_found'), NotificationType::Error));

            return false;
        }

        return true;
    }

    /**
     * Un alta arranca en blanco: vaciar el estado de Alpine no alcanza, el form
     * del server sigue con el estado que se abrió antes.
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
        unset($this->statuses);

        $this->dispatch('catalog-rows-refreshed', rows: $this->statuses);
    }

    /**
     * Estados para el riel de Alpine. Se entregan una sola vez al montar: el
     * buscador y el contador filtran client-side, sin request al server.
     *
     * El `id` viaja siempre: es la única clave estable para editar. El `name` es
     * editable por el usuario, así que no sirve para identificar la fila.
     *
     * @return \Illuminate\Support\Collection<int, array{id: int, name: string}>
     */
    #[Computed]
    public function statuses(): \Illuminate\Support\Collection
    {
        return CurrentStatus::query()
            ->orderBy('name')
            ->get()
            ->map(
                fn(CurrentStatus $status): array => [
                    'id' => $status->id,
                    'name' => $status->name,
                ],
            )
            ->values();
    }
};
?>

<x-catalog.master :rows="$initialRows" path="form.currentStatusData" :blank="['name' => '']" :search="['name']"
    :rules="[
        'name' => ['required', ['minLength', 3], ['maxLength', 255], 'noMarkup'],
    ]">

    {{-- ============ VISTA TABLA (el "mostrar") ============ --}}
    <x-slot:list>
        <x-catalog.toolbar :search-placeholder="__('catalog.status.search_placeholder')"
            :search-label="__('catalog.status.search_label')" :singular="__('catalog.status.singular')"
            :plural="__('catalog.status.plural')" :create="__('catalog.status.create')" />

        <x-catalog.table :empty="__('catalog.status.empty')" :columns="[
            ['label' => __('catalog.status.columns.name'), 'class' => 'catalog-col-name'],
        ]">
            <td class="catalog-cell-name" x-text="row.name"></td>
        </x-catalog.table>
    </x-slot:list>

    {{-- ============ VISTA FORMULARIO (crear / editar) ============ --}}
    <x-slot:form>
        <x-catalog.form-shell :new="__('catalog.status.new')" :new-title="__('catalog.status.new_title')"
            :edit-title="__('catalog.status.edit_title')" :create="__('catalog.status.create')">

            {{-- Un solo campo: ocupa la fila entera, que es exactamente lo que
                 pide la regla —la fila llega al borde con lo que tenga. --}}
            <x-catalog.form-row>
                <x-inputsform.input span="text" :label="__('catalog.status.fields.name')" required name="name"
                    :placeholder="__('catalog.status.fields.name_placeholder')" alpine-error="name"
                    wire:model="form.currentStatusData.name" />
            </x-catalog.form-row>
        </x-catalog.form-shell>
    </x-slot:form>
</x-catalog.master>
