<?php

use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\Catalog\BusinessActivityForm;
use App\Models\BusinessActivity;
use App\Models\BusinessSector;
use App\Traits\HasNotifications;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Editor del maestro Actividades (tabla `business_activities`).
 *
 * El chrome (toolbar, tabla, barra del form, pie de acciones) y todo el riel de
 * Alpine viven en `<x-catalog.*>` y en `catalogMaster()`: acá quedan SOLO las
 * acciones del server y los campos propios del maestro. Livewire 4 nativo (SFC).
 */
new class extends Component {
    use HasNotifications;

    public BusinessActivityForm $form;

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

        $this->initialRows = $this->businessActivities->all();
    }

    /**
     * Devuelve si se guardó, para que Alpine sepa si volver a la lista o dejar
     * al usuario en el formulario con lo que escribió.
     */
    public function create(): bool
    {
        $notification = $this->form->storeBusinessActivity();

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
        $notification = $this->form->updateBusinessActivity();

        $this->dispatchNotification($notification);

        if ($notification->type !== NotificationType::Success) {
            return false;
        }

        $this->resetForm();

        $this->reloadTable();

        return true;
    }

    /**
     * Devuelve si se pudo abrir. Si la actividad ya no existe avisa y el front se
     * queda en la lista, en vez de mostrar un formulario vacío o un 404 crudo.
     */
    public function openEdit(int $businessActivityId): bool
    {
        if (!$this->form->loadBusinessActivityData($businessActivityId)) {
            $this->dispatchNotification(new NotificationDto(__('notifications.not_found'), NotificationType::Error));

            return false;
        }

        return true;
    }

    /**
     * Un alta arranca en blanco: vaciar el estado de Alpine no alcanza, el form
     * del server sigue con la actividad que se abrió antes.
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
        unset($this->businessActivities);

        $this->dispatch('catalog-rows-refreshed', rows: $this->businessActivities);
    }

    /**
     * Actividades para el riel de Alpine. Se entregan una sola vez al montar: el
     * buscador y el contador filtran client-side, sin request al server.
     */
    #[Computed]
    public function businessActivities(): \Illuminate\Support\Collection
    {
        return new BusinessActivity()->catalogRows();
    }

    /**
     * Opciones del combobox de rubro. Van TODOS, también los inactivos: si una
     * actividad ya apunta a un rubro dado de baja, filtrarlo acá haría que al
     * abrirla el combobox apareciera vacío y el guardado le cambiara el rubro sin
     * que nadie lo tocara.
     *
     * @return array<int, array{value: int, label: string}>
     */
    #[Computed]
    public function sectorOptions(): array
    {
        return BusinessSector::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(
                fn(BusinessSector $sector): array => [
                    'value' => $sector->id,
                    'label' => $sector->name,
                ],
            )
            ->all();
    }
};
?>

<x-catalog.master :rows="$initialRows" path="form.businessActivityData" :blank="['code' => '', 'name' => '', 'sector' => '', 'order' => 0, 'active' => true]" :search="['code', 'name', 'sector']"
    :rules="[
        'code' => ['required', ['minLength', 2], ['maxLength', 40], 'noMarkup'],
        'name' => ['required', ['minLength', 3], ['maxLength', 255], 'noMarkup'],
        'business_sector_id' => ['required'],
        'sort_order' => ['integer', ['min', 0], ['max', 32767]],
    ]">

    {{-- ============ VISTA TABLA (el "mostrar") ============ --}}
    <x-slot:list>
        <x-catalog.toolbar :search-placeholder="__('catalog.business_activity.search_placeholder')" :search-label="__('catalog.business_activity.search_label')" :singular="__('catalog.business_activity.singular')" :plural="__('catalog.business_activity.plural')" :create="__('catalog.business_activity.create')" />

        <x-catalog.table :empty="__('catalog.business_activity.empty')" :columns="[
            ['label' => __('catalog.business_activity.columns.code')],
            ['label' => __('catalog.business_activity.columns.name'), 'class' => 'catalog-col-fill'],
            ['label' => __('catalog.business_activity.columns.sector')],
            ['label' => __('catalog.business_activity.columns.order')],
            ['label' => __('catalog.business_activity.columns.status')],
        ]">
            <td><span class="catalog-code" x-text="row.code"></span></td>
            <td class="catalog-cell-name catalog-cell-fill" x-text="row.name"></td>
            <td x-text="row.sector"></td>
            <td class="catalog-cell-sym" x-text="row.order"></td>
            <td>
                <span class="catalog-status" x-bind:class="row.active ? 'is-on' : 'is-off'">
                    <span class="dot"></span><span
                        x-text="row.active ? {{ \Illuminate\Support\Js::from(__('catalog.business_activity.status.active')) }} : {{ \Illuminate\Support\Js::from(__('catalog.business_activity.status.inactive')) }}"></span>
                </span>
            </td>
        </x-catalog.table>
    </x-slot:list>

    {{-- ============ VISTA FORMULARIO (crear / editar) ============ --}}
    <x-slot:form>
        <x-catalog.form-shell :new="__('catalog.business_activity.new')" :new-title="__('catalog.business_activity.new_title')" :edit-title="__('catalog.business_activity.edit_title')" :create="__('catalog.business_activity.create')"
            title-key="name">

            {{-- Fila 1: la clave corta y el nombre, que se lleva todo el resto. --}}
            <x-catalog.form-row>
                <x-inputsform.input span="code" :label="__('catalog.business_activity.fields.code')" required name="code" :hint="__('catalog.business_activity.fields.code_hint')"
                    maxlength="40" alpine-error="code" wire:model="form.businessActivityData.code" />

                <x-inputsform.input span="text" :label="__('catalog.business_activity.fields.name')" required name="name" :placeholder="__('catalog.business_activity.fields.name_placeholder')"
                    alpine-error="name" wire:model="form.businessActivityData.name" />
            </x-catalog.form-row>

            {{-- Fila 2: el rubro del que cuelga, la descripción que absorbe el
                 sobrante, y el orden y el estado cerrando la línea. --}}
            <x-catalog.form-row>
                <x-inputsform.combobox span="text" :label="__('catalog.business_activity.fields.sector')" required name="business_sector_id"
                    :placeholder="__('catalog.business_activity.fields.sector_placeholder')" :options="$this->sectorOptions" :value="$form->businessActivityData?->business_sector_id" alpine-error="business_sector_id"
                    wire:model="form.businessActivityData.business_sector_id" />

                <x-inputsform.input span="long" :label="__('catalog.business_activity.fields.description')" name="description" :placeholder="__('catalog.business_activity.fields.description_placeholder')"
                    :hint="__('catalog.business_activity.fields.description_hint')" maxlength="255" alpine-error="description"
                    wire:model="form.businessActivityData.description" />

                <x-inputsform.input span="code" :label="__('catalog.business_activity.fields.order')" name="sort_order" type="number" min="0"
                    max="32767" :hint="__('catalog.business_activity.fields.order_hint')" alpine-error="sort_order"
                    wire:model="form.businessActivityData.sort_order" />

                <x-inputsform.switch-field span="short" :label="__('catalog.business_activity.fields.status')" name="is_active" :on="__('catalog.business_activity.status.active')"
                    :off="__('catalog.business_activity.status.inactive')" wire:model="form.businessActivityData.is_active" />
            </x-catalog.form-row>
        </x-catalog.form-shell>
    </x-slot:form>
</x-catalog.master>
