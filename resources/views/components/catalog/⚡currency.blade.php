<?php

use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\Catalog\CurrencyForm;
use App\Models\Currency;
use App\Traits\HasNotifications;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Editor del maestro Monedas (tabla `currencies`). MAQUETA de diseño: la vista
 * tabla → formulario (swap) con datos de muestra en Alpine. Las propiedades y
 * acciones reales (listar/crear/editar/guardar/eliminar sobre el modelo
 * Currency, validación server-side y autorización) se cablean después.
 * Livewire 4 nativo (SFC), no Volt.
 */
new class extends Component {
    use HasNotifications;

    public CurrencyForm $form;

    /**
     * Semilla del riel de Alpine, CONGELADA al montar.
     *
     * No puede salir de la computed: `x-data="currencyMaster(...)"` lleva el JSON
     * embebido, así que si la lista cambia (al guardar) cambia el atributo, Livewire
     * lo re-renderiza y Alpine RE-INICIALIZA el componente — se pierde `mode`, `view`
     * y todo. Síntoma: la primera edición tras el refresh anda, y después de guardar
     * el editor cree que estás creando. La tabla se mantiene al día por el evento
     * `currencies-refreshed`, no por este atributo.
     *
     * @var array<int, array<string, mixed>>
     */
    #[Locked]
    public array $initialRows = [];

    public function mount(): void
    {
        $this->form->setup();

        $this->initialRows = $this->currencies->all();
    }

    /**
     * Devuelve si se guardó, para que Alpine sepa si volver a la lista o dejar
     * al usuario en el formulario con lo que escribió. Sin este booleano el
     * front no distinguía éxito de error y quedaba en un form ya vaciado.
     */
    public function create(): bool
    {
        $notification = $this->form->storeCurrency();

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
        $notification = $this->form->updateCurrency();

        $this->dispatchNotification($notification);

        if ($notification->type !== NotificationType::Success) {
            return false;
        }

        $this->resetForm();

        $this->reloadTable();

        return true;
    }

    /**
     * Devuelve si se pudo abrir. Si la moneda ya no existe avisa y el front se
     * queda en la lista, en vez de mostrar un formulario vacío o un 404 crudo.
     */
    public function openEdit(int $currencyId): bool
    {
        if (! $this->form->loadCurrencyData($currencyId)) {
            $this->dispatchNotification(
                new NotificationDto(__('notifications.not_found'), NotificationType::Error),
            );

            return false;
        }

        return true;
    }

    /**
     * Un alta arranca en blanco. Vaciar el estado de Alpine no alcanza: el form
     * del server sigue con la moneda que se abrió antes, así que "Nueva moneda"
     * aparecía con los datos de esa otra (y su `currencyId`).
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
        unset($this->currencies);

        $this->dispatch('currencies-refreshed', currencies: $this->currencies);
    }

    /**
     * Monedas para el riel de Alpine. Se entregan una sola vez al montar: el
     * buscador y el contador filtran client-side, sin request al server.
     *
     * El `id` viaja siempre: es la única clave estable para editar. El `code` es
     * editable por el usuario, así que no sirve para identificar la fila.
     *
     * @return \Illuminate\Support\Collection<int, array{id: int, code: string, name: string, symbol: string, decimals: int, active: bool}>
     */
    #[Computed]
    public function currencies(): \Illuminate\Support\Collection
    {
        return Currency::query()
            ->orderBy('code')
            ->get()
            ->map(
                fn(Currency $currency): array => [
                    'id' => $currency->id,
                    'code' => $currency->code,
                    'name' => $currency->name,
                    'symbol' => $currency->symbol,
                    'decimals' => $currency->decimal_places,
                    'active' => $currency->is_active,
                ],
            )
            ->values();
    }
};
?>

<div x-data="currencyMaster(@js($initialRows))" class="catalog-master" x-on:currencies-refreshed="items = $event.detail.currencies">
    {{-- ============ VISTA TABLA (el "mostrar") ============ --}}
    <div class="catalog-view" x-show="view === 'list'">
        <div class="catalog-toolbar">
            <x-inputsform.input name="q" size="s" icon="search" placeholder="{{ __('catalog.currency.search_placeholder') }}"
                x-model="q" aria-label="{{ __('catalog.currency.search_label') }}" />
            <span class="catalog-count"><b x-text="filtered().length"></b> <span
                    x-text="filtered().length === 1 ? @js(__('catalog.currency.singular')) : @js(__('catalog.currency.plural'))"></span></span>
            <x-ui.button variant="primary" icon="plus" x-on:click="openCreate()">{{ __('catalog.currency.create') }}</x-ui.button>
        </div>

        <div class="catalog-table-wrap">
            <table class="catalog-table">
                <thead>
                    <tr>
                        <th>{{ __('catalog.currency.columns.code') }}</th>
                        <th class="catalog-col-name">{{ __('catalog.currency.columns.name') }}</th>
                        <th>{{ __('catalog.currency.columns.symbol') }}</th>
                        <th class="is-num">{{ __('catalog.currency.columns.decimals') }}</th>
                        <th>{{ __('catalog.currency.columns.status') }}</th>
                        <th class="catalog-gocell" aria-hidden="true"></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="c in filtered()" :key="c.id">
                        <tr x-on:click="openEdit(c)">
                            <td><span class="catalog-code" x-text="c.code"></span></td>
                            <td class="catalog-cell-name" x-text="c.name"></td>
                            <td class="catalog-cell-sym" x-text="c.symbol"></td>
                            <td class="catalog-cell-num" x-text="c.decimals"></td>
                            <td>
                                <span class="catalog-status" x-bind:class="c.active ? 'is-on' : 'is-off'">
                                    <span class="dot"></span><span x-text="c.active ? @js(__('catalog.currency.status.active')) : @js(__('catalog.currency.status.inactive'))"></span>
                                </span>
                            </td>
                            <td class="catalog-gocell">
                                <span class="catalog-row-go"><x-icon name="chevron-right" :size="16" /></span>
                            </td>
                        </tr>
                    </template>

                    <tr x-show="filtered().length === 0">
                        <td colspan="6" class="catalog-table-empty">{{ __('catalog.currency.empty') }}</td>
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
            <span class="catalog-form-badge" x-text="mode === 'edit' ? @js(__('catalog.common.editing')) : @js(__('catalog.currency.new'))"></span>
            <span class="catalog-form-title">
                <template x-if="mode === 'edit'">
                    <span>{{ __('catalog.currency.edit_title') }} <span class="mono" x-text="f.code"></span></span>
                </template>
                <template x-if="mode === 'create'"><span>{{ __('catalog.currency.new_title') }}</span></template>
            </span>
        </div>

        <form class="catalog-form" x-on:submit.prevent="submit">
            {{-- Identificador chico + nombre a lo ancho --}}
            <div class="col-4">
                <x-inputsform.input label="{{ __('catalog.currency.fields.code') }}" required name="code" hint="{{ __('catalog.currency.fields.code_hint') }}" maxlength="3"
                    alpine-error="code" x-mask="aaa" style="text-transform:uppercase"
                    wire:model="form.currencyData.code" />
            </div>
            <div class="col-8">
                <x-inputsform.input label="{{ __('catalog.currency.fields.name') }}" required name="name" placeholder="{{ __('catalog.currency.fields.name_placeholder') }}"
                    alpine-error="name" wire:model="form.currencyData.name" />
            </div>

            {{-- Formato: símbolo + decimales, cortos, compartiendo fila --}}
            <div class="col-7">
                <x-inputsform.input label="{{ __('catalog.currency.fields.symbol') }}" required name="symbol" hint="{{ __('catalog.currency.fields.symbol_hint') }}"
                    maxlength="5" alpine-error="symbol" wire:model="form.currencyData.symbol" />
            </div>
            <div class="col-5">
                <x-inputsform.input label="{{ __('catalog.currency.fields.decimals') }}" name="decimal_places" type="number" min="0" max="2"
                    alpine-error="decimal_places" wire:model="form.currencyData.decimal_places" />
            </div>

            {{-- Estado, al pie --}}
            <div class="col-12">
                <div class="catalog-switch-row">
                    <div>
                        <p class="catalog-switch-title">{{ __('catalog.currency.active_title') }}</p>
                        <p class="catalog-switch-desc">{{ __('catalog.currency.active_desc') }}</p>
                    </div>
                    <x-ui.switch name="is_active" wire:model="form.currencyData.is_active" />
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
                <span x-text="mode === 'edit' ? @js(__('catalog.common.save')) : @js(__('catalog.currency.create'))"></span>
            </x-ui.button>
        </div>
    </div>
</div>

@script
    <script>
        Alpine.data('currencyMaster', (items = []) => ({
            view: 'list',
            mode: 'create',
            q: '',
            errors: {},
            items,

            // id === null => alta. Con id => edición de esa fila, pase lo que pase con el code.
            f: {
                id: null,
                code: '',
                name: '',
                symbol: '',
                decimals: 2,
                active: true
            },

            filtered() {
                const q = this.q.trim().toLowerCase();
                if (!q) return this.items;
                return this.items.filter(c =>
                    c.code.toLowerCase().includes(q) || c.name.toLowerCase().includes(q));
            },

            async openCreate() {
                this.mode = 'create';
                this.errors = {};
                this.f = {
                    id: null,
                    code: '',
                    name: '',
                    symbol: '',
                    decimals: 2,
                    active: true
                };
                // El server también tiene que arrancar en blanco, si no el alta
                // hereda los datos y el id de la moneda que se editó antes.
                await this.$wire.openCreate();
                this.view = 'form';
            },

            async openEdit(c) {
                // OJO con el orden: `mode` y `f` se setean YA, antes del await, igual
                // que en openCreate(). Si se setean después, entre el click y la
                // respuesta del server queda una ventana con el mode de la vez
                // anterior — y si venías de "Crear moneda", el form abre diciendo
                // "Nueva". Lo único que espera al server es `view`, para no mostrar
                // un formulario de una moneda que ya no existe.
                this.mode = 'edit';
                this.errors = {};
                this.f = {
                    ...c
                };

                if (!await this.$wire.openEdit(c.id)) {
                    return;
                }

                this.view = 'form';
            },

            backToList() {
                this.view = 'list';
            },

            async submit() {
                // Espejo de CurrencyForm::getValidationRules(). Lo único que no se
                // puede replicar acá es el `unique` del código: eso necesita la BD,
                // así que ese rebote sigue viniendo del server.
                this.errors = validate({
                    code: this.$wire.get('form.currencyData.code'),
                    name: this.$wire.get('form.currencyData.name'),
                    symbol: this.$wire.get('form.currencyData.symbol'),
                    decimal_places: this.$wire.get('form.currencyData.decimal_places'),
                }, {
                    code: ['required', 'alpha', ['length', 3]],
                    name: ['required', ['minLength', 3], ['maxLength', 255], 'noMarkup'],
                    symbol: ['required', ['minLength', 1], ['maxLength', 5], 'noMarkup'],
                    decimal_places: ['required', 'integer', ['min', 0], ['max', 2]],
                });

                if (Object.keys(this.errors).length > 0) {
                    return;
                }

                // Si guardó, el server ya vació el form: hay que volver a la lista
                // o el usuario se queda mirando un formulario en blanco que sigue
                // diciendo "Editar ARS". Si no guardó, se queda con lo que escribió.
                const saved = this.mode === 'edit' ?
                    await this.$wire.update() :
                    await this.$wire.create();

                if (saved) {
                    this.backToList();
                }
            },

            remove() {
                // Maqueta: sin persistencia.
                this.backToList();
            },
        }));
    </script>
@endscript
