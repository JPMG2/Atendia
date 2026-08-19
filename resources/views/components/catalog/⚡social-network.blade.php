<?php

use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\Catalog\SocialNetworkForm;
use App\Models\SocialNetwork;
use App\Traits\HasNotifications;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Editor del maestro Redes sociales (tabla `social_networks`).
 *
 * El chrome (toolbar, tabla, barra del form, pie de acciones) y todo el riel de
 * Alpine viven en `<x-catalog.*>` y en `catalogMaster()`: acá quedan SOLO las
 * acciones del server y los campos propios del maestro. Livewire 4 nativo (SFC).
 */
new class extends Component {
    use HasNotifications;

    public SocialNetworkForm $form;

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

        $this->initialRows = $this->socialNetworks->all();
    }

    /**
     * Devuelve si se guardó, para que Alpine sepa si volver a la lista o dejar
     * al usuario en el formulario con lo que escribió.
     */
    public function create(): bool
    {
        $notification = $this->form->storeSocialNetwork();

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
        $notification = $this->form->updateSocialNetwork();

        $this->dispatchNotification($notification);

        if ($notification->type !== NotificationType::Success) {
            return false;
        }

        $this->resetForm();

        $this->reloadTable();

        return true;
    }

    /**
     * Devuelve si se pudo abrir. Si la red ya no existe avisa y el front se queda
     * en la lista, en vez de mostrar un formulario vacío o un 404 crudo.
     */
    public function openEdit(int $socialNetworkId): bool
    {
        if (!$this->form->loadSocialNetworkData($socialNetworkId)) {
            $this->dispatchNotification(new NotificationDto(__('notifications.not_found'), NotificationType::Error));

            return false;
        }

        return true;
    }

    /**
     * Un alta arranca en blanco: vaciar el estado de Alpine no alcanza, el form
     * del server sigue con la red que se abrió antes.
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
        unset($this->socialNetworks);

        $this->dispatch('catalog-rows-refreshed', rows: $this->socialNetworks);
    }

    /**
     * Redes para el riel de Alpine. Se entregan una sola vez al montar: el buscador
     * y el contador filtran client-side, sin request al server.
     *
     * El `id` viaja siempre: es la única clave estable para editar. El `name` es
     * editable por el usuario, así que no sirve para identificar la fila.
     *
     * @return \Illuminate\Support\Collection<int, array{id: int, name: string, url: string, icon: string, abbreviation: string, active: bool}>
     */
    #[Computed]
    public function socialNetworks(): \Illuminate\Support\Collection
    {
        return SocialNetwork::query()
            ->orderBy('name')
            ->get()
            ->map(
                fn(SocialNetwork $network): array => [
                    'id' => $network->id,
                    'name' => $network->name,
                    'url' => $network->url,
                    // Las columnas son nullable y Alpine pinta el valor crudo: un
                    // null saldría como "null" en la celda, así que viaja vacío.
                    'icon' => $network->icon ?? '',
                    'abbreviation' => $network->abbreviation ?? '',
                    'active' => $network->is_active,
                ],
            )
            ->values();
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

<x-catalog.master :rows="$initialRows" path="form.socialNetworkData"
    :blank="['name' => '', 'url' => '', 'icon' => '', 'abbreviation' => '', 'active' => true]"
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
            ['label' => __('catalog.social_network.columns.name'), 'class' => 'catalog-col-name'],
            ['label' => __('catalog.social_network.columns.abbreviation')],
            ['label' => __('catalog.social_network.columns.url')],
            ['label' => __('catalog.social_network.columns.icon')],
            ['label' => __('catalog.social_network.columns.status')],
        ]">
            <td class="catalog-cell-name" x-text="row.name"></td>
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
                    alpine-error="abbreviation" wire:model="form.socialNetworkData.abbreviation" />

                <x-inputsform.input span="text" :label="__('catalog.social_network.fields.name')" required name="name"
                    :placeholder="__('catalog.social_network.fields.name_placeholder')" alpine-error="name"
                    wire:model="form.socialNetworkData.name" />
            </x-catalog.form-row>

            {{-- Fila 2: el resto de los campos repartiéndose el ancho completo. --}}
            <x-catalog.form-row>
                <x-inputsform.input span="long" :label="__('catalog.social_network.fields.url')" required name="url"
                    type="url" :hint="__('catalog.social_network.fields.url_hint')" maxlength="255" alpine-error="url"
                    wire:model="form.socialNetworkData.url" />

                <x-inputsform.combobox span="text" :label="__('catalog.social_network.fields.icon')" name="icon"
                    :placeholder="__('catalog.social_network.fields.icon_placeholder')"
                    :hint="__('catalog.social_network.fields.icon_hint')" :options="$this->iconOptions"
                    :value="$form->socialNetworkData?->icon" alpine-error="icon"
                    wire:model="form.socialNetworkData.icon" />

                <x-inputsform.switch-field span="text" :label="__('catalog.social_network.fields.status')"
                    name="is_active" :on="__('catalog.social_network.status.active')"
                    :off="__('catalog.social_network.status.inactive')"
                    wire:model="form.socialNetworkData.is_active" />
            </x-catalog.form-row>

        </x-catalog.form-shell>
    </x-slot:form>
</x-catalog.master>
