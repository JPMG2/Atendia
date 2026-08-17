<?php

use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\Catalog\CountryForm;
use App\Models\Country;
use App\Models\Currency;
use App\Traits\HasNotifications;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Editor del maestro Países (tabla `countries`). Mismo patrón que el maestro
 * Monedas: vista tabla → formulario (swap) en Alpine, y las acciones reales
 * (listar/crear/editar) sobre el modelo Country a través de CountryForm.
 * Livewire 4 nativo (SFC).
 */
new class extends Component {
    use HasNotifications;

    public CountryForm $form;

    /**
     * Semilla del riel de Alpine, CONGELADA al montar.
     *
     * No puede salir de la computed: `x-data="countryMaster(...)"` lleva el JSON
     * embebido, así que si la lista cambia (al guardar) cambia el atributo, Livewire
     * lo re-renderiza y Alpine RE-INICIALIZA el componente — se pierde `mode`, `view`
     * y todo. Síntoma: la primera edición tras el refresh anda, y después de guardar
     * el editor cree que estás creando. La tabla se mantiene al día por el evento
     * `countries-refreshed`, no por este atributo.
     *
     * @var array<int, array<string, mixed>>
     */
    #[Locked]
    public array $initialRows = [];

    public function mount(): void
    {
        $this->form->setup();

        $this->initialRows = $this->countries->all();
    }

    /**
     * Devuelve si se guardó, para que Alpine sepa si volver a la lista o dejar
     * al usuario en el formulario con lo que escribió. Sin este booleano el
     * front no distinguía éxito de error y quedaba en un form ya vaciado.
     */
    public function create(): bool
    {
        $notification = $this->form->storeCountry();

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
        $notification = $this->form->updateCountry();

        $this->dispatchNotification($notification);

        if ($notification->type !== NotificationType::Success) {
            return false;
        }

        $this->resetForm();

        $this->reloadTable();

        return true;
    }

    /**
     * Devuelve si se pudo abrir. Si el país ya no existe avisa y el front se
     * queda en la lista, en vez de mostrar un formulario vacío o un 404 crudo.
     */
    public function openEdit(int $countryId): bool
    {
        if (!$this->form->loadCountryData($countryId)) {
            $this->dispatchNotification(new NotificationDto(__('notifications.not_found'), NotificationType::Error));

            return false;
        }

        return true;
    }

    /**
     * Un alta arranca en blanco. Vaciar el estado de Alpine no alcanza: el form
     * del server sigue con el país que se abrió antes, así que "Nuevo país"
     * aparecía con los datos de ese otro (y su `countryId`).
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
        unset($this->countries);

        $this->dispatch('countries-refreshed', countries: $this->countries);
    }

    /**
     * Países para el riel de Alpine. Se entregan una sola vez al montar: el
     * buscador y el contador filtran client-side, sin request al server.
     *
     * El `id` viaja siempre: es la única clave estable para editar. El `code` es
     * editable por el usuario, así que no sirve para identificar la fila.
     *
     * @return \Illuminate\Support\Collection<int, array{id: int, code: string, name: string, phone_code: string|null, currency: string, active: bool}>
     */
    #[Computed]
    public function countries(): \Illuminate\Support\Collection
    {
        return Country::query()
            ->with('currency:id,code')
            ->orderBy('name')
            ->get()
            ->map(
                fn(Country $country): array => [
                    'id' => $country->id,
                    'code' => $country->code,
                    'name' => $country->name,
                    'phone_code' => $country->phone_code,
                    'currency' => $country->currency?->code ?? '',
                    'active' => $country->is_active,
                ],
            )
            ->values();
    }

    /**
     * Opciones del select de moneda. Van TODAS, también las inactivas: si un país
     * ya apunta a una moneda dada de baja, filtrarla acá haría que al abrir ese
     * país el select apareciera vacío y el guardado le cambiara la moneda sin que
     * nadie la tocara.
     *
     * @return array<int, array{value: int, label: string}>
     */
    #[Computed]
    public function currencyOptions(): array
    {
        return Currency::query()
            ->orderBy('code')
            ->get()
            ->map(
                fn(Currency $currency): array => [
                    'value' => $currency->id,
                    'label' => $currency->code . ' — ' . $currency->name,
                ],
            )
            ->all();
    }
};
?>

<div x-data="countryMaster(@js($initialRows))" class="catalog-master" x-on:countries-refreshed="items = $event.detail.countries">
    {{-- ============ VISTA TABLA (el "mostrar") ============ --}}
    <div class="catalog-view" x-show="view === 'list'">
        <div class="catalog-toolbar">
            <x-inputsform.input name="q" size="s" icon="search"
                placeholder="{{ __('catalog.country.search_placeholder') }}" x-model="q"
                aria-label="{{ __('catalog.country.search_label') }}" />
            <span class="catalog-count"><b x-text="filtered().length"></b> <span
                    x-text="filtered().length === 1 ? @js(__('catalog.country.singular')) : @js(__('catalog.country.plural'))"></span></span>
            <x-ui.button variant="primary" icon="plus"
                x-on:click="openCreate()">{{ __('catalog.country.create') }}</x-ui.button>
        </div>

        <div class="catalog-table-wrap">
            <table class="catalog-table">
                <thead>
                    <tr>
                        <th>{{ __('catalog.country.columns.code') }}</th>
                        <th class="catalog-col-name">{{ __('catalog.country.columns.name') }}</th>
                        <th>{{ __('catalog.country.columns.phone_code') }}</th>
                        <th>{{ __('catalog.country.columns.currency') }}</th>
                        <th>{{ __('catalog.country.columns.status') }}</th>
                        <th class="catalog-gocell" aria-hidden="true"></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="c in filtered()" :key="c.id">
                        <tr x-on:click="openEdit(c)">
                            <td><span class="catalog-code" x-text="c.code"></span></td>
                            <td class="catalog-cell-name" x-text="c.name"></td>
                            <td class="catalog-cell-sym" x-text="c.phone_code"></td>
                            <td class="catalog-cell-sym" x-text="c.currency"></td>
                            <td>
                                <span class="catalog-status" x-bind:class="c.active ? 'is-on' : 'is-off'">
                                    <span class="dot"></span><span
                                        x-text="c.active ? @js(__('catalog.country.status.active')) : @js(__('catalog.country.status.inactive'))"></span>
                                </span>
                            </td>
                            <td class="catalog-gocell">
                                <span class="catalog-row-go"><x-icon name="chevron-right" :size="16" /></span>
                            </td>
                        </tr>
                    </template>

                    <tr x-show="filtered().length === 0">
                        <td colspan="6" class="catalog-table-empty">{{ __('catalog.country.empty') }}</td>
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
                x-text="mode === 'edit' ? @js(__('catalog.common.editing')) : @js(__('catalog.country.new'))"></span>
            <span class="catalog-form-title">
                <template x-if="mode === 'edit'">
                    <span>{{ __('catalog.country.edit_title') }} <span class="mono" x-text="f.code"></span></span>
                </template>
                <template x-if="mode === 'create'"><span>{{ __('catalog.country.new_title') }}</span></template>
            </span>
        </div>

        <form class="catalog-form" x-on:submit.prevent="submit">
            {{-- Identificador chico + nombre a lo ancho --}}
            <div class="col-4">
                <x-inputsform.input label="{{ __('catalog.country.fields.code') }}" required name="code"
                    hint="{{ __('catalog.country.fields.code_hint') }}" maxlength="3" alpine-error="code"
                    x-mask="aaa" style="text-transform:uppercase" wire:model="form.countryData.code" />
            </div>
            <div class="col-8">
                <x-inputsform.input label="{{ __('catalog.country.fields.name') }}" required name="name"
                    placeholder="{{ __('catalog.country.fields.name_placeholder') }}" alpine-error="name"
                    wire:model="form.countryData.name" />
            </div>

            {{-- Atributos: código telefónico corto + la moneda, que es descriptiva --}}
            <div class="col-4">
                <x-inputsform.input label="{{ __('catalog.country.fields.phone_code') }}" name="phone_code"
                    hint="{{ __('catalog.country.fields.phone_code_hint') }}" maxlength="6" alpine-error="phone_code"
                    wire:model="form.countryData.phone_code" />
            </div>
            <div class="col-8">
                <x-inputsform.combobox label="{{ __('catalog.country.fields.currency') }}" required name="currency_id"
                    placeholder="{{ __('catalog.country.fields.currency_placeholder') }}" :options="$this->currencyOptions"
                    :value="$form->countryData?->currency_id" alpine-error="currency_id" wire:model="form.countryData.currency_id" />
            </div>

            {{-- Estado, al pie --}}
            <div class="col-12">
                <div class="catalog-switch-row">
                    <div>
                        <p class="catalog-switch-title">{{ __('catalog.country.active_title') }}</p>
                        <p class="catalog-switch-desc">{{ __('catalog.country.active_desc') }}</p>
                    </div>
                    <x-ui.switch name="is_active" wire:model="form.countryData.is_active" />
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
                <span x-text="mode === 'edit' ? @js(__('catalog.common.save')) : @js(__('catalog.country.create'))"></span>
            </x-ui.button>
        </div>
    </div>
</div>

@script
    <script>
        Alpine.data('countryMaster', (items = []) => ({
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
                phone_code: '',
                currency: '',
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
                    phone_code: '',
                    currency: '',
                    active: true
                };
                // El server también tiene que arrancar en blanco, si no el alta
                // hereda los datos y el id del país que se editó antes.
                await this.$wire.openCreate();
                this.view = 'form';
            },

            async openEdit(c) {
                // OJO con el orden: `mode` y `f` se setean YA, antes del await, igual
                // que en openCreate(). Si se setean después, entre el click y la
                // respuesta del server queda una ventana con el mode de la vez
                // anterior — y si venías de "Crear país", el form abre diciendo
                // "Nuevo". Lo único que espera al server es `view`, para no mostrar
                // un formulario de un país que ya no existe.
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
                // Espejo de CountryForm::getValidationRules(). Lo que no se puede
                // replicar acá es el `unique` del código y del nombre, y el `exists`
                // de la moneda: eso necesita la BD, así que ese rebote sigue
                // viniendo del server. `phone_code` solo se acota por largo: la
                // regla del server admite + ( ) y guiones, y validate() no tiene un
                // check equivalente — inventarle uno más estricto acá rebotaría
                // valores que el server sí acepta.
                this.errors = validate({
                    code: this.$wire.get('form.countryData.code'),
                    name: this.$wire.get('form.countryData.name'),
                    phone_code: this.$wire.get('form.countryData.phone_code'),
                    currency_id: this.$wire.get('form.countryData.currency_id'),
                }, {
                    code: ['required', 'alpha', ['length', 3]],
                    name: ['required', ['minLength', 3],
                        ['maxLength', 255], 'noMarkup'
                    ],
                    phone_code: [
                        ['maxLength', 6]
                    ],
                    currency_id: ['required'],
                });

                if (Object.keys(this.errors).length > 0) {
                    return;
                }

                // Si guardó, el server ya vació el form: hay que volver a la lista
                // o el usuario se queda mirando un formulario en blanco que sigue
                // diciendo "Editar ARG". Si no guardó, se queda con lo que escribió.
                const saved = this.mode === 'edit' ?
                    await this.$wire.update() :
                    await this.$wire.create();

                if (saved) {
                    this.backToList();
                }
            },

            remove() {
                // Igual que en Monedas: la baja todavía no está cableada.
                this.backToList();
            },
        }));
    </script>
@endscript
