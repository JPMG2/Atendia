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
 * Editor del maestro Redes sociales (tabla `social_networks`). Mismo patrón que
 * los maestros Monedas y Países: vista tabla → formulario (swap) en Alpine, y las
 * acciones reales (listar/crear/editar) sobre el modelo SocialNetwork a través de
 * SocialNetworkForm. Livewire 4 nativo (SFC).
 */
new class extends Component {
    use HasNotifications;

    public SocialNetworkForm $form;

    /**
     * Semilla del riel de Alpine, CONGELADA al montar.
     *
     * No puede salir de la computed: `x-data="socialNetworkMaster(...)"` lleva el
     * JSON embebido, así que si la lista cambia (al guardar) cambia el atributo,
     * Livewire lo re-renderiza y Alpine RE-INICIALIZA el componente — se pierde
     * `mode`, `view` y todo. Síntoma: la primera edición tras el refresh anda, y
     * después de guardar el editor cree que estás creando. La tabla se mantiene al
     * día por el evento `social-networks-refreshed`, no por este atributo.
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
     * al usuario en el formulario con lo que escribió. Sin este booleano el
     * front no distinguía éxito de error y quedaba en un form ya vaciado.
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
     * Un alta arranca en blanco. Vaciar el estado de Alpine no alcanza: el form
     * del server sigue con la red que se abrió antes, así que "Nueva red social"
     * aparecía con los datos de esa otra (y su `socialNetworkId`).
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

        $this->dispatch('social-networks-refreshed', socialNetworks: $this->socialNetworks);
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

<div x-data="socialNetworkMaster(@js($initialRows))" class="catalog-master"
    x-on:social-networks-refreshed="items = $event.detail.socialNetworks">
    {{-- ============ VISTA TABLA (el "mostrar") ============ --}}
    <div class="catalog-view" x-show="view === 'list'">
        <div class="catalog-toolbar">
            <x-inputsform.input name="q" size="s" icon="search"
                placeholder="{{ __('catalog.social_network.search_placeholder') }}" x-model="q"
                aria-label="{{ __('catalog.social_network.search_label') }}" />
            <span class="catalog-count"><b x-text="filtered().length"></b> <span
                    x-text="filtered().length === 1 ? @js(__('catalog.social_network.singular')) : @js(__('catalog.social_network.plural'))"></span></span>
            <x-ui.button variant="primary" icon="plus"
                x-on:click="openCreate()">{{ __('catalog.social_network.create') }}</x-ui.button>
        </div>

        <div class="catalog-table-wrap">
            <table class="catalog-table">
                <thead>
                    <tr>
                        <th class="catalog-col-name">{{ __('catalog.social_network.columns.name') }}</th>
                        <th>{{ __('catalog.social_network.columns.abbreviation') }}</th>
                        <th>{{ __('catalog.social_network.columns.url') }}</th>
                        <th>{{ __('catalog.social_network.columns.icon') }}</th>
                        <th>{{ __('catalog.social_network.columns.status') }}</th>
                        <th class="catalog-gocell" aria-hidden="true"></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="n in filtered()" :key="n.id">
                        <tr x-on:click="openEdit(n)">
                            <td class="catalog-cell-name" x-text="n.name"></td>
                            <td><span class="catalog-code" x-text="n.abbreviation"></span></td>
                            <td class="catalog-cell-sym" x-text="n.url"></td>
                            <td class="catalog-cell-sym" x-text="n.icon"></td>
                            <td>
                                <span class="catalog-status" x-bind:class="n.active ? 'is-on' : 'is-off'">
                                    <span class="dot"></span><span
                                        x-text="n.active ? @js(__('catalog.social_network.status.active')) : @js(__('catalog.social_network.status.inactive'))"></span>
                                </span>
                            </td>
                            <td class="catalog-gocell">
                                <span class="catalog-row-go"><x-icon name="chevron-right" :size="16" /></span>
                            </td>
                        </tr>
                    </template>

                    <tr x-show="filtered().length === 0">
                        <td colspan="6" class="catalog-table-empty">{{ __('catalog.social_network.empty') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- ============ VISTA FORMULARIO (crear / editar) ============ --}}
    <div class="catalog-view" x-show="view === 'form'" x-cloak>
        <div class="catalog-formbar">
            <button type="button" class="catalog-back" x-on:click="backToList()">
                <x-icon name="chevron-left" :size="15" /> {{ __('catalog.common.back') }}
            </button>
            <span class="catalog-form-badge"
                x-text="mode === 'edit' ? @js(__('catalog.common.editing')) : @js(__('catalog.social_network.new'))"></span>
            <span class="catalog-form-title">
                <template x-if="mode === 'edit'">
                    <span>{{ __('catalog.social_network.edit_title') }} <span class="mono" x-text="f.name"></span></span>
                </template>
                <template x-if="mode === 'create'"><span>{{ __('catalog.social_network.new_title') }}</span></template>
            </span>
        </div>

        <form class="catalog-form" x-on:submit.prevent="submit">
            {{-- Nombre a lo ancho (identifica la red) + abreviatura, que es corta --}}
            <div class="col-8">
                <x-inputsform.input label="{{ __('catalog.social_network.fields.name') }}" required name="name"
                    placeholder="{{ __('catalog.social_network.fields.name_placeholder') }}" alpine-error="name"
                    wire:model="form.socialNetworkData.name" />
            </div>
            <div class="col-4">
                <x-inputsform.input label="{{ __('catalog.social_network.fields.abbreviation') }}" name="abbreviation"
                    hint="{{ __('catalog.social_network.fields.abbreviation_hint') }}" maxlength="10"
                    alpine-error="abbreviation" wire:model="form.socialNetworkData.abbreviation" />
            </div>

            {{-- La URL es el campo más largo del maestro: fila completa, sin truncar --}}
            <div class="col-12">
                <x-inputsform.input label="{{ __('catalog.social_network.fields.url') }}" required name="url"
                    type="url" hint="{{ __('catalog.social_network.fields.url_hint') }}" maxlength="255"
                    alpine-error="url" wire:model="form.socialNetworkData.url" />
            </div>

            {{-- Visualización: el glifo con el que se dibuja la red --}}
            <div class="col-12">
                <x-inputsform.combobox label="{{ __('catalog.social_network.fields.icon') }}" name="icon"
                    placeholder="{{ __('catalog.social_network.fields.icon_placeholder') }}"
                    hint="{{ __('catalog.social_network.fields.icon_hint') }}" :options="$this->iconOptions"
                    :value="$form->socialNetworkData?->icon" alpine-error="icon"
                    wire:model="form.socialNetworkData.icon" />
            </div>

            {{-- Estado, al pie --}}
            <div class="col-12">
                <div class="catalog-switch-row">
                    <div>
                        <p class="catalog-switch-title">{{ __('catalog.social_network.active_title') }}</p>
                        <p class="catalog-switch-desc">{{ __('catalog.social_network.active_desc') }}</p>
                    </div>
                    <x-ui.switch name="is_active" wire:model="form.socialNetworkData.is_active" />
                </div>
            </div>
        </form>

        <div class="catalog-form-foot">
            <template x-if="mode === 'edit'">
                <x-ui.button variant="ghost" icon="trash-2" class="catalog-btn-danger"
                    x-on:click="remove()">{{ __('catalog.common.delete') }}</x-ui.button>
            </template>
            <span class="catalog-foot-grow"></span>
            <x-ui.button variant="ghost" x-on:click="backToList()">{{ __('catalog.common.cancel') }}</x-ui.button>
            <x-ui.button variant="primary" icon="check" x-on:click="submit()">
                <span x-text="mode === 'edit' ? @js(__('catalog.common.save')) : @js(__('catalog.social_network.create'))"></span>
            </x-ui.button>
        </div>
    </div>
</div>

@script
    <script>
        Alpine.data('socialNetworkMaster', (items = []) => ({
            view: 'list',
            mode: 'create',
            q: '',
            errors: {},
            items,

            // id === null => alta. Con id => edición de esa fila, pase lo que pase con el nombre.
            f: {
                id: null,
                name: '',
                url: '',
                icon: '',
                abbreviation: '',
                active: true
            },

            filtered() {
                const q = this.q.trim().toLowerCase();
                if (!q) return this.items;
                return this.items.filter(n =>
                    n.name.toLowerCase().includes(q) || n.abbreviation.toLowerCase().includes(q));
            },

            async openCreate() {
                this.mode = 'create';
                this.errors = {};
                this.f = {
                    id: null,
                    name: '',
                    url: '',
                    icon: '',
                    abbreviation: '',
                    active: true
                };
                // El server también tiene que arrancar en blanco, si no el alta
                // hereda los datos y el id de la red que se editó antes.
                await this.$wire.openCreate();
                this.view = 'form';
            },

            async openEdit(n) {
                // OJO con el orden: `mode` y `f` se setean YA, antes del await, igual
                // que en openCreate(). Si se setean después, entre el click y la
                // respuesta del server queda una ventana con el mode de la vez
                // anterior — y si venías de "Crear red social", el form abre diciendo
                // "Nueva". Lo único que espera al server es `view`, para no mostrar
                // un formulario de una red que ya no existe.
                this.mode = 'edit';
                this.errors = {};
                this.f = {
                    ...n
                };

                if (!await this.$wire.openEdit(n.id)) {
                    return;
                }

                this.view = 'form';
            },

            backToList() {
                this.view = 'list';
            },

            async submit() {
                // Espejo de SocialNetworkForm::getValidationRules(). Lo que no se
                // puede replicar acá es el `unique` del nombre y el `in` del ícono
                // contra config/icons.php: eso necesita el server. La URL solo se
                // acota por largo: validate() no tiene un check de URL, e inventarle
                // uno más estricto rebotaría direcciones que el server sí acepta.
                this.errors = validate({
                    name: this.$wire.get('form.socialNetworkData.name'),
                    url: this.$wire.get('form.socialNetworkData.url'),
                    abbreviation: this.$wire.get('form.socialNetworkData.abbreviation'),
                }, {
                    name: ['required', ['minLength', 3],
                        ['maxLength', 255], 'noMarkup'
                    ],
                    url: ['required', ['maxLength', 255], 'noMarkup'],
                    abbreviation: [
                        ['maxLength', 10], 'noMarkup'
                    ],
                });

                if (Object.keys(this.errors).length > 0) {
                    return;
                }

                // Si guardó, el server ya vació el form: hay que volver a la lista
                // o el usuario se queda mirando un formulario en blanco que sigue
                // diciendo "Editar Instagram". Si no guardó, se queda con lo que escribió.
                const saved = this.mode === 'edit' ?
                    await this.$wire.update() :
                    await this.$wire.create();

                if (saved) {
                    this.backToList();
                }
            },

            remove() {
                // Igual que en Monedas y Países: la baja todavía no está cableada.
                this.backToList();
            },
        }));
    </script>
@endscript
