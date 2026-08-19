<?php

use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\Catalog\TaxConditionForm;
use App\Models\Country;
use App\Models\TaxCondition;
use App\Traits\HasNotifications;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Editor del maestro Condiciones fiscales (tabla `tax_conditions`).
 *
 * El chrome (toolbar, tabla, barra del form, pie de acciones) y todo el riel de
 * Alpine viven en `<x-catalog.*>` y en `catalogMaster()`: acá quedan SOLO las
 * acciones del server y los campos propios del maestro. Livewire 4 nativo (SFC).
 */
new class extends Component {
    use HasNotifications;

    public TaxConditionForm $form;

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

        $this->initialRows = $this->taxConditions->all();
    }

    /**
     * Devuelve si se guardó, para que Alpine sepa si volver a la lista o dejar
     * al usuario en el formulario con lo que escribió.
     */
    public function create(): bool
    {
        $notification = $this->form->storeTaxCondition();

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
        $notification = $this->form->updateTaxCondition();

        $this->dispatchNotification($notification);

        if ($notification->type !== NotificationType::Success) {
            return false;
        }

        $this->resetForm();

        $this->reloadTable();

        return true;
    }

    /**
     * Devuelve si se pudo abrir. Si la condición ya no existe avisa y el front se
     * queda en la lista, en vez de mostrar un formulario vacío o un 404 crudo.
     */
    public function openEdit(int $taxConditionId): bool
    {
        if (!$this->form->loadTaxConditionData($taxConditionId)) {
            $this->dispatchNotification(new NotificationDto(__('notifications.not_found'), NotificationType::Error));

            return false;
        }

        return true;
    }

    /**
     * Un alta arranca en blanco: vaciar el estado de Alpine no alcanza, el form
     * del server sigue con la condición que se abrió antes.
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
        unset($this->taxConditions);

        $this->dispatch('catalog-rows-refreshed', rows: $this->taxConditions);
    }

    /**
     * Condiciones fiscales para el riel de Alpine. Se entregan una sola vez al
     * montar: el buscador y el contador filtran client-side, sin request al server.
     *
     * El `id` viaja siempre: es la única clave estable para editar. El `code` es
     * editable por el usuario, así que no sirve para identificar la fila.
     *
     * @return \Illuminate\Support\Collection<int, array{id: int, code: string, name: string, country: string, discriminates: bool, active: bool}>
     */
    #[Computed]
    public function taxConditions(): \Illuminate\Support\Collection
    {
        return TaxCondition::query()
            ->with('country:id,code')
            ->orderBy('code')
            ->get()
            ->map(
                fn(TaxCondition $condition): array => [
                    'id' => $condition->id,
                    'code' => $condition->code,
                    'name' => $condition->name,
                    'country' => $condition->country?->code ?? '',
                    'discriminates' => $condition->discriminate_tax,
                    'active' => $condition->is_active,
                ],
            )
            ->values();
    }

    /**
     * Opciones del combobox de país. Van TODOS, también los inactivos: si una
     * condición ya apunta a un país dado de baja, filtrarlo acá haría que al
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

<x-catalog.master :rows="$initialRows" path="form.taxConditionData"
    :blank="['code' => '', 'name' => '', 'country' => '', 'discriminates' => false, 'active' => true]"
    :search="['code', 'name', 'country']"
    :rules="[
        'code' => ['required', ['minLength', 2], ['maxLength', 255], 'noMarkup'],
        'name' => ['required', ['minLength', 3], ['maxLength', 255], 'noMarkup'],
        'country_id' => ['required'],
    ]">

    {{-- ============ VISTA TABLA (el "mostrar") ============ --}}
    <x-slot:list>
        <x-catalog.toolbar :search-placeholder="__('catalog.tax_condition.search_placeholder')"
            :search-label="__('catalog.tax_condition.search_label')" :singular="__('catalog.tax_condition.singular')"
            :plural="__('catalog.tax_condition.plural')" :create="__('catalog.tax_condition.create')" />

        <x-catalog.table :empty="__('catalog.tax_condition.empty')" :columns="[
            ['label' => __('catalog.tax_condition.columns.code')],
            ['label' => __('catalog.tax_condition.columns.name'), 'class' => 'catalog-col-fill'],
            ['label' => __('catalog.tax_condition.columns.country')],
            ['label' => __('catalog.tax_condition.columns.discriminate_tax')],
            ['label' => __('catalog.tax_condition.columns.status')],
        ]">
            <td><span class="catalog-code" x-text="row.code"></span></td>
            <td class="catalog-cell-name catalog-cell-fill" x-text="row.name"></td>
            <td class="catalog-cell-sym" x-text="row.country"></td>
            <td x-text="row.discriminates ? {{ \Illuminate\Support\Js::from(__('catalog.tax_condition.discriminate.yes')) }} : {{ \Illuminate\Support\Js::from(__('catalog.tax_condition.discriminate.no')) }}"></td>
            <td>
                <span class="catalog-status" x-bind:class="row.active ? 'is-on' : 'is-off'">
                    <span class="dot"></span><span
                        x-text="row.active ? {{ \Illuminate\Support\Js::from(__('catalog.tax_condition.status.active')) }} : {{ \Illuminate\Support\Js::from(__('catalog.tax_condition.status.inactive')) }}"></span>
                </span>
            </td>
        </x-catalog.table>
    </x-slot:list>

    {{-- ============ VISTA FORMULARIO (crear / editar) ============ --}}
    <x-slot:form>
        <x-catalog.form-shell :new="__('catalog.tax_condition.new')" :new-title="__('catalog.tax_condition.new_title')"
            :edit-title="__('catalog.tax_condition.edit_title')" :create="__('catalog.tax_condition.create')"
            title-key="code">

            {{-- Fila 1: el código corto y el nombre, que se lleva todo el resto. --}}
            <x-catalog.form-row>
                <x-inputsform.input span="code" :label="__('catalog.tax_condition.fields.code')" required name="code"
                    :hint="__('catalog.tax_condition.fields.code_hint')" maxlength="10" alpine-error="code"
                    style="text-transform:uppercase" wire:model="form.taxConditionData.code" />

                <x-inputsform.input span="text" :label="__('catalog.tax_condition.fields.name')" required name="name"
                    :placeholder="__('catalog.tax_condition.fields.name_placeholder')" alpine-error="name"
                    wire:model="form.taxConditionData.name" />
            </x-catalog.form-row>

            {{-- Fila 2: el resto repartiéndose el ancho completo, los dos
                 booleanos incluidos. --}}
            <x-catalog.form-row>
                <x-inputsform.combobox span="text" :label="__('catalog.tax_condition.fields.country')" required
                    name="country_id" :placeholder="__('catalog.tax_condition.fields.country_placeholder')"
                    :options="$this->countryOptions" :value="$form->taxConditionData?->country_id"
                    alpine-error="country_id" wire:model="form.taxConditionData.country_id" />

                <x-inputsform.switch-field span="short" :label="__('catalog.tax_condition.fields.discriminate_tax')"
                    name="discriminate_tax" :on="__('catalog.tax_condition.discriminate.yes')"
                    :off="__('catalog.tax_condition.discriminate.no')"
                    wire:model="form.taxConditionData.discriminate_tax" />

                <x-inputsform.switch-field span="short" :label="__('catalog.tax_condition.fields.status')"
                    name="is_active" :on="__('catalog.tax_condition.status.active')"
                    :off="__('catalog.tax_condition.status.inactive')" wire:model="form.taxConditionData.is_active" />
            </x-catalog.form-row>
        </x-catalog.form-shell>
    </x-slot:form>
</x-catalog.master>
