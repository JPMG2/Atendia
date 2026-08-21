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
     */
    #[Computed]
    public function statuses(): \Illuminate\Support\Collection
    {
        return new CurrentStatus()->catalogRows();
    }

    /**
     * Paleta para el combobox. Sale de `CurrentStatus::COLORS` y no de una lista
     * escrita en el Blade: la clave que se guarda y la que el CSS sabe pintar
     * tienen que ser la MISMA, y la validación usa esa misma constante.
     *
     * @return array<int, array{value: string, label: string}>
     */
    #[Computed]
    public function colorOptions(): array
    {
        return collect(CurrentStatus::COLORS)
            ->map(fn(string $color): array => [
                'value' => $color,
                'label' => __('catalog.status.colors.' . $color),
            ])
            ->all();
    }
};
?>

<x-catalog.master :rows="$initialRows" path="form.currentStatusData" :blank="['name' => '', 'color' => \App\Models\CurrentStatus::DEFAULT_COLOR]" :search="['name']"
    :rules="[
        'name' => ['required', ['minLength', 3], ['maxLength', 255], 'noMarkup'],
    ]">

    {{-- ============ VISTA TABLA (el "mostrar") ============ --}}
    <x-slot:list>
        <x-catalog.toolbar :search-placeholder="__('catalog.status.search_placeholder')"
            :search-label="__('catalog.status.search_label')" :singular="__('catalog.status.singular')"
            :plural="__('catalog.status.plural')" :create="__('catalog.status.create')" />

        {{-- Con solo dos columnas el que absorbe el sobrante es el COLOR, no el
             nombre: así el tag queda pegado al nombre en vez de irse al borde
             derecho dejando un hueco enorme en el medio. --}}
        <x-catalog.table :empty="__('catalog.status.empty')" :columns="[
            ['label' => __('catalog.status.columns.name')],
            ['label' => __('catalog.status.columns.color'), 'class' => 'catalog-col-fill'],
        ]">
            <td class="catalog-cell-name" x-text="row.name"></td>
            {{-- El tag se muestra tal cual se va a ver en el resto del programa:
                 el color no se escribe acá, sale de la clave guardada en la fila. --}}
            <td class="catalog-cell-fill">
                <span class="status-tag" x-bind:class="'is-' + row.color">
                    <span class="dot"></span><span x-text="row.name"></span>
                </span>
            </td>
        </x-catalog.table>
    </x-slot:list>

    {{-- ============ VISTA FORMULARIO (crear / editar) ============ --}}
    <x-slot:form>
        <x-catalog.form-shell :new="__('catalog.status.new')" :new-title="__('catalog.status.new_title')"
            :edit-title="__('catalog.status.edit_title')" :create="__('catalog.status.create')">

            {{-- Nombre y color en una fila: el nombre se lleva el sobrante. --}}
            <x-catalog.form-row>
                <x-inputsform.input span="text" :label="__('catalog.status.fields.name')" required name="name"
                    :placeholder="__('catalog.status.fields.name_placeholder')" alpine-error="name"
                    wire:model="form.currentStatusData.name" />

                <x-inputsform.combobox span="text" :label="__('catalog.status.fields.color')" required name="color"
                    :placeholder="__('catalog.status.fields.color_placeholder')"
                    :hint="__('catalog.status.fields.color_hint')" :options="$this->colorOptions"
                    :value="$form->currentStatusData?->color" alpine-error="color"
                    wire:model="form.currentStatusData.color" />
            </x-catalog.form-row>
        </x-catalog.form-shell>
    </x-slot:form>
</x-catalog.master>
