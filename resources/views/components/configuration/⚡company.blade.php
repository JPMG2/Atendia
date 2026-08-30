<?php

use App\Enums\NotificationType;
use App\Livewire\Forms\Configuration\CompanyForm;
use App\Models\Country;
use App\Models\Province;
use App\Models\Region;
use App\Models\SocialNetwork;
use App\Models\TaxCondition;
use App\Traits\HasNotifications;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Compañía: los datos de AtendIa — UN solo registro, no los negocios clientes.
 *
 * La carga va por PASOS: el segundo recién se abre cuando la compañía existe,
 * porque lo que lo desbloquea es haber guardado el primero. Con el registro ya
 * cargado los dos pasos quedan libres.
 *
 * El paso 1 ya guarda: valida en el front, después en el server, y persiste
 * SOLO sus columnas. El paso 2 sigue sin acción, y las redes sociales siguen
 * siendo maqueta: todavía no se cargan desde `social_links`.
 */
new #[Title('Compañía')] class extends Component {
    use HasNotifications;

    public CompanyForm $form;

    /**
     * ¿La compañía ya está cargada? Es lo que abre el segundo paso.
     *
     * Va `#[Locked]` y se setea UNA vez en `mount()` a propósito: siembra el
     * `x-data` del stepper, y si el JSON embebido cambiara entre renders Livewire
     * reescribiría el atributo y Alpine re-inicializaría el componente, perdiendo
     * en qué paso estaba parado el usuario. El desbloqueo en caliente viaja por
     * el evento `stepper-unlock`, no por este valor.
     */
    #[Locked]
    public bool $isRegistered = false;

    /**
     * Cuántas veces se descartó el paso 1. Va en el `wire:key` de su panel.
     *
     * Los campos van con `wire:model` deferred: lo que el usuario tipea NO viaja
     * hasta que hay un request. Al descartar, el server repone valores que en su
     * lado muchas veces YA eran esos, así que el HTML sale idéntico, Livewire no
     * parchea nada y en pantalla sobrevive lo tipeado —el combobox vaciado se
     * quedaba vacío aunque la región estuviera restaurada—. Cambiar la clave
     * fuerza a rehacer el panel entero desde el server, que es exactamente lo que
     * significa descartar.
     */
    #[Locked]
    public int $mainRevision = 0;

    /** Lo mismo para el panel del paso 2. Ver {@see self::$mainRevision}. */
    #[Locked]
    public int $commercialRevision = 0;

    /**
     * Deja el DTO cargado ANTES del primer render.
     *
     * `setup()` no es un hook de Livewire Form: si no se lo llama desde acá, el
     * DTO queda en null y el primer `wire:model` explota con "Cannot assign
     * array to property".
     */
    public function mount(): void
    {
        $this->form->setup();

        $this->isRegistered = $this->form->recordId !== null;
    }

    /**
     * Guarda el paso principal. Devuelve si guardó.
     *
     * El front usa ese booleano para desbloquear el paso 2 y avanzar; si rebotó,
     * el usuario se queda donde está con lo que escribió.
     *
     * OJO: acá NO se toca `$isRegistered`. Siembra el `x-data` del stepper, y si
     * cambiara de valor Livewire reescribiría el atributo y Alpine
     * re-inicializaría el componente —perdiendo el paso, el scroll y lo tipeado—.
     * El desbloqueo en caliente lo hace el evento `stepper-unlock`.
     *
     * Sin `authorize()`: la ruta ya exige `access-admin-panel` y ese middleware
     * se vuelve a aplicar en cada request de Livewire (persistent middleware),
     * igual que en los editores de catálogo.
     */
    public function saveMain(): bool
    {
        $notification = $this->form->saveMain();

        $this->dispatchNotification($notification);

        return $notification->type !== NotificationType::Error;
    }

    /**
     * Guarda el paso comercial (contacto y redes). Devuelve si guardó.
     */
    public function saveCommercial(): bool
    {
        $notification = $this->form->saveCommercial();

        $this->dispatchNotification($notification);

        return $notification->type !== NotificationType::Error;
    }

    /**
     * Descarta lo escrito en el paso comercial y vuelve a lo guardado.
     */
    public function discardCommercial(): void
    {
        $this->form->discardCommercial();

        $this->resetValidation();

        $this->commercialRevision++;
    }

    /** Suma una fila de red debajo de la indicada. */
    public function addSocialRow(int $after): void
    {
        $this->form->addSocialRow($after);
    }

    /**
     * Quita una fila de red. Si ya estaba guardada, el borrado es inmediato.
     *
     * La advertencia la pide la pantalla ANTES de llamar acá, por el diálogo del
     * sistema; la autorización sigue siendo la del panel (el middleware admin se
     * re-aplica en cada request de Livewire).
     */
    public function removeSocialRow(int $index): void
    {
        $notification = $this->form->removeSocialRow($index);

        if ($notification !== null) {
            $this->dispatchNotification($notification);
        }
    }

    /**
     * Descarta lo escrito en el paso principal y vuelve a lo guardado.
     *
     * También limpia el rebote del server: un campo en rojo de un intento que se
     * acaba de descartar no describe nada de lo que hay ahora en pantalla.
     */
    public function discardMain(): void
    {
        $this->form->discardMain();

        $this->resetValidation();

        $this->mainRevision++;
    }

    /**
     * Países activos para el combobox.
     *
     * El catálogo antepone el código ("ARG — Argentina") para poder buscar por
     * él; acá el país se elige por su nombre, así que la pantalla pasa su
     * propio label en vez de arrastrar un código que nadie va a tipear.
     *
     * @return array<int, array{value: int, label: string}>
     */
    #[Computed]
    public function countryOptions(): array
    {
        return Country::options(states: [true], label: fn(Country $country): string => $country->name);
    }

    /**
     * Provincias del país elegido.
     *
     * No hace falta recargarla desde ningún hook: la computed LEE
     * `form.data.country_id`, así que al cambiar el país el próximo render ya
     * sale con la lista nueva. Sin país elegido salen todas.
     *
     * Mismo criterio de etiqueta que el país: el catálogo cuelga el código del
     * país para distinguir dos provincias homónimas, y acá alcanza con el
     * nombre porque el país ya quedó elegido en el campo de arriba.
     *
     * @return array<int, array{value: int, label: string}>
     */
    #[Computed]
    public function provinceOptions(): array
    {
        return $this->form->data?->country_id ? Province::options(states: [true], label: fn(Province $province): string => $province->name, countryId: $this->form->data?->country_id) : [];
    }

    /**
     * Regiones activas para el combobox.
     *
     * @return array<int, array{value: int, label: string}>
     */
    #[Computed]
    public function regionOptions(): array
    {
        return $this->form->data?->province_id ? Region::options(states: [true], provinceId: $this->form->data?->province_id) : [];
    }

    /**
     * Condiciones fiscales activas para el combobox.
     *
     * @return array<int, array{value: int, label: string}>
     */
    #[Computed]
    public function taxConditionOptions(): array
    {
        return $this->form->data?->country_id ? TaxCondition::options(states: [true], countryId: $this->form->data?->country_id) : [];
    }

    /**
     * Redes sociales del catálogo, para elegir en cada fila.
     *
     * @return array<int, array{value: int, label: string}>
     */
    #[Computed]
    public function socialOptions(): array
    {
        return SocialNetwork::options(states: [true]);
    }
};
?>

{{-- Changing step empties the bag: otherwise step one greets you with a
field in red from an attempt you had stopped looking at. --}}
<div x-data="companyForm" x-on:step-changed="errors = {}">
    <div class="page-head">
        <div>
            <h1 class="page-head-title">{{ __('company.title') }}</h1>
            <p class="page-head-sub">{{ __('company.subtitle') }}</p>
        </div>
    </div>

    {{-- Step two starts locked until the company exists: the commercial data
    hangs off a record that is not there yet. With it loaded there is
    nothing to order and both steps are free. --}}
    <x-ui.stepper default="main" :unlocked="$isRegistered" :lockedHint="__('company.steps.locked_hint')" :steps="[
        ['value' => 'main', 'label' => __('company.steps.main.label'), 'desc' => __('company.steps.main.desc')],
        ['value' => 'commercial', 'label' => __('company.steps.commercial.label'), 'desc' => __('company.steps.commercial.desc')],
    ]">

        {{-- Company details. The order they are filled in: who it is, the number it
        invoices under, where it is, how it looks, what closes the
        invoice. --}}
        <x-ui.card class="mt-4" x-show="step === 'main'" x-cloak wire:key="step-main-{{ $mainRevision }}">

            <div class="config-block">
                <div>
                    <h2 class="config-block-title">{{ __('company.identity.title') }}</h2>
                    <p class="config-block-desc">{{ __('company.identity.desc') }}</p>
                </div>

                <div class="config-fields catalog-form">
                    <x-catalog.form-row>
                        <x-inputsform.input span="text" required name="legal_name" alpine-error="legal_name"
                            :label="__('company.fields.legal_name')" :placeholder="__('company.fields.legal_name_placeholder')" wire:model="form.data.legal_name" />

                        <x-inputsform.input span="long" name="tagline" :label="__('company.fields.tagline')" :placeholder="__('company.fields.tagline_placeholder')"
                            :hint="__('company.fields.tagline_hint')" wire:model="form.data.tagline" />
                    </x-catalog.form-row>
                </div>
            </div>

            <div class="config-block">
                <div>
                    <h2 class="config-block-title">{{ __('company.address.title') }}</h2>
                    <p class="config-block-desc">{{ __('company.address.desc') }}</p>
                </div>

                {{-- Broad to narrow: country, province, region, and only then the
                street. The country goes first because everything else hangs
                off it, the tax standing included. --}}
                <div class="config-fields catalog-form">
                    <x-catalog.form-row>
                        <x-inputsform.combobox span="text" required name="country_id" alpine-error="country_id"
                            :label="__('company.fields.country')" :placeholder="__('company.fields.country_placeholder')" :options="$this->countryOptions" :value="$form->data?->country_id"
                            wire:model.live="form.data.country_id" />

                        <x-inputsform.combobox span="text" required name="province_id" alpine-error="province_id"
                            :label="__('company.fields.province')" :placeholder="__('company.fields.province_placeholder')" :options="$this->provinceOptions" :value="$form->data?->province_id"
                            loading="form.data.country_id" wire:model.live="form.data.province_id" />
                    </x-catalog.form-row>

                    <x-catalog.form-row>
                        <x-inputsform.combobox span="text" required name="region_id" alpine-error="region_id"
                            :label="__('company.fields.region')" :placeholder="__('company.fields.region_placeholder')" :options="$this->regionOptions" :value="$form->data?->region_id"
                            loading="form.data.province_id" wire:model="form.data.region_id" />

                        <x-inputsform.input span="long" required name="address" alpine-error="address"
                            :label="__('company.fields.address')" :placeholder="__('company.fields.address_placeholder')" maxlength="255" wire:model="form.data.address" />
                    </x-catalog.form-row>
                </div>
            </div>

            <div class="config-block">
                <div>
                    <h2 class="config-block-title">{{ __('company.tax.title') }}</h2>
                    <p class="config-block-desc">{{ __('company.tax.desc') }}</p>
                </div>

                {{-- The standing comes first: it is what says which number belongs
                here, not the other way round. --}}
                <div class="config-fields catalog-form">
                    <x-catalog.form-row>
                        <x-inputsform.combobox span="text" required name="tax_condition_id"
                            alpine-error="tax_condition_id" :label="__('company.fields.tax_condition')" :placeholder="__('company.fields.tax_condition_placeholder')" :options="$this->taxConditionOptions"
                            :value="$form->data?->tax_condition_id" wire:model="form.data.tax_condition_id" />

                        <x-inputsform.input span="long" required name="tax_id" alpine-error="tax_id" class="font-mono" :label="__('company.fields.tax_id')"
                            :hint="__('company.fields.tax_id_hint')" maxlength="20" wire:model="form.data.tax_id" />
                    </x-catalog.form-row>
                </div>
            </div>


            <div class="config-block">
                <div>
                    <h2 class="config-block-title">{{ __('company.logo.title') }}</h2>
                    <p class="config-block-desc">{{ __('company.logo.desc') }}</p>
                </div>

                <div class="config-logo-grid">
                    @foreach ([['name' => 'logo_path_light', 'label' => __('company.logo.light')], ['name' => 'logo_path_dark', 'label' => __('company.logo.dark')]] as $logo)
                        <div class="field">
                            <x-ui.label :for="'if-' . $logo['name']">{{ $logo['label'] }}</x-ui.label>

                            {{-- Not wired: the drop zone takes no files yet. The real control
                            will be an <x-ui.*> with its test, and only then does
                            it bind to the model. --}}
                            <button type="button" class="config-drop" id="if-{{ $logo['name'] }}">
                                <span class="config-drop-icon"><x-icon name="upload" :size="22" /></span>
                                <span>{{ __('company.logo.upload') }}</span>
                                <span class="config-drop-hint">{{ __('company.logo.hint') }}</span>
                            </button>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="config-block">
                <div>
                    <h2 class="config-block-title">{{ __('company.footer.title') }}</h2>
                    <p class="config-block-desc">{{ __('company.footer.desc') }}</p>
                </div>

                <div class="config-fields catalog-form">
                    <x-catalog.form-row>
                        <x-inputsform.input span="full" name="text_copyright" :label="__('company.fields.copyright')" :placeholder="__('company.fields.copyright_placeholder')"
                            maxlength="255" wire:model="form.data.text_copyright" />
                    </x-catalog.form-row>
                </div>
            </div>
        </x-ui.card>

        {{-- Contact details and social networks. --}}
        <x-ui.card class="mt-4" x-show="step === 'commercial'" x-cloak
            wire:key="step-commercial-{{ $commercialRevision }}">

            <div class="config-block">
                <div>
                    <h2 class="config-block-title">{{ __('company.contact.title') }}</h2>
                    <p class="config-block-desc">{{ __('company.contact.desc') }}</p>
                </div>

                <div class="config-fields catalog-form">
                    {{-- Two rows and not three cramped fields: a phone number in mono
                    needs its width, and truncating is not compacting. --}}
                    <x-catalog.form-row>
                        <x-inputsform.input span="text" name="email" alpine-error="email" type="email" icon="mail"
                            :label="__('company.fields.email')" :placeholder="__('company.fields.email_placeholder')" wire:model="form.data.email" />

                        <x-inputsform.input span="text" name="phone" alpine-error="phone" icon="phone" class="font-mono"
                            :label="__('company.fields.phone')" :placeholder="__('company.fields.phone_placeholder')" maxlength="30" wire:model="form.data.phone" />
                    </x-catalog.form-row>

                    <x-catalog.form-row>
                        <x-inputsform.input span="long" name="web" alpine-error="web" type="url" icon="globe"
                            :label="__('company.fields.web')" :placeholder="__('company.fields.web_placeholder')" wire:model="form.data.web" />
                    </x-catalog.form-row>
                </div>
            </div>

            {{-- The networks are not a fixed list: a company adds the ones it has
            and drops the ones it stops using. Each row is one. --}}
            <div class="config-block">
                <div>
                    <h2 class="config-block-title">{{ __('company.social.title') }}</h2>
                    <p class="config-block-desc">{{ __('company.social.desc') }}</p>
                </div>

                <div class="config-fields catalog-form">
                    <div class="config-social">
                        <div class="config-social-row config-social-head">
                            <span class="f-text field-label">{{ __('company.social.network') }}</span>
                            <span class="f-long field-label">{{ __('company.social.url') }}</span>
                            <span class="config-social-spacer" aria-hidden="true"></span>
                        </div>

                        {{-- Remove and add live on the SAME line as the row: a loose
                        button underneath breaks the alignment and forces the
                        eye down to add the next one. One row always
                        remains, or there is nowhere to start again. --}}
                        {{-- The rows live on the SERVER and not in Alpine: a row that only
                        exists in the browser cannot be saved. The `wire:key`
                        carries the row's key and not its index, so removing
                        one from the middle moves the right node. --}}
                        @foreach ($form->social as $index => $row)
                            <div class="config-social-row" wire:key="social-{{ $row['key'] }}">
                                <x-inputsform.combobox span="text" :id="'if-social-' . $index . '-network'"
                                    :name="'social.' . $index . '.social_network_id'" :aria-label="__('company.social.network')"
                                    :placeholder="__('company.social.network_placeholder')" :options="$this->socialOptions" :value="$row['social_network_id']"
                                    wire:model="form.social.{{ $index }}.social_network_id" />

                                <x-inputsform.input span="long" icon="link" :id="'if-social-' . $index . '-url'"
                                    :name="'social.' . $index . '.url'" :aria-label="__('company.social.url')"
                                    :placeholder="__('company.social.url_placeholder')" maxlength="255"
                                    wire:model="form.social.{{ $index }}.url" />

                                {{-- Removing a saved network deletes it THERE AND THEN, so it
                                warns first, through the system dialog — AtendIa
                                has no native browser confirmations. A row that
                                was never saved goes without asking. --}}
                                <x-ui.icon-button icon="trash-2" variant="ghost" class="config-social-remove"
                                    data-testid="social-remove"
                                    :label="__('company.social.remove')" :disabled="count($form->social) === 1 && $row['id'] === null"
                                    x-on:click="removeSocial({{ $index }}, {{ $row['id'] !== null ? 'true' : 'false' }})" />

                                <x-ui.icon-button icon="plus" variant="ghost" class="config-social-add"
                                    :label="__('company.social.add')" data-testid="social-add"
                                    wire:click="addSocialRow({{ $index }})" />
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </x-ui.card>

        {{-- The actions close the screen, same as the masters' footer: save is
        looked for at the end of a form, not above it.

        It goes in once and INSIDE the stepper — the footer belongs to the
        screen and not to a tab, and in there it can read which step the
        person is on, which is what decides who the button hits. --}}
        <div class="catalog-form-foot config-foot">
            <span class="catalog-foot-grow"></span>
            <x-ui.button variant="ghost" x-on:click="discard(step)">{{ __('company.discard') }}</x-ui.button>

            {{-- With no company yet, saving step one is what opens step two, and
            the label says so instead of leaving people guessing why the
            other step is still locked. The server sends the text already
            in the right state, so there is no flicker. --}}
            <x-ui.button variant="primary" icon="check" x-on:click="submit(step)">
                <span
                    x-text="step === 'main' && ! unlocked ? @js(__('company.save_continue')) : @js(__('company.save'))"
                >{{ $isRegistered ? __('company.save') : __('company.save_continue') }}</span>
            </x-ui.button>
        </div>
    </x-ui.stepper>
</div>

@script
    <script>
        /*
         * Validación en el FRONT de la pantalla Compañía.
         *
         * Mismo criterio que los maestros del catálogo: las reglas espejan lo que
         * la pantalla marca con asterisco, y el guardado no sale hasta que estén
         * completas. Lo que NO se puede replicar acá es lo que necesita la base
         * (`exists` de los combobox): ese rebote sigue viniendo del server.
         *
         * Se apoya en la función madre global `validate()` (form-guard.js), que
         * ya viene cargada en el layout del dashboard.
         */
        Alpine.data('companyForm', () => ({
            errors: {},

            // Dónde vive el DTO en el server. Los valores se leen de ahí y no de
            // Alpine: los campos van por wire:model contra el DTO, que es el
            // único estado del formulario.
            path: 'form.data',

            // Una bolsa POR PASO, no una sola para toda la pantalla: si el paso
            // parado validara también las reglas del otro, el error se pintaría
            // en un panel oculto y el botón parecería no hacer nada.
            rules: {
                main: {
                    legal_name: ['required', ['maxLength', 255], 'noMarkup'],
                    country_id: ['required'],
                    province_id: ['required'],
                    region_id: ['required'],
                    address: ['required', ['maxLength', 255], 'noMarkup'],
                    tax_condition_id: ['required'],
                    tax_id: ['required', ['maxLength', 20], 'noMarkup'],
                },

                // El formato de la URL y la red repetida NO se pueden replicar
                // acá (una es `url:` de Laravel, la otra necesita mirar el resto
                // de las filas): esos rebotan del server, como el `exists`.
                commercial: {
                    email: ['email', ['maxLength', 255]],
                    phone: [['maxLength', 30]],
                    web: [['maxLength', 255], 'noMarkup'],
                },
            },

            /**
             * Quita una red. Si ya estaba guardada, advierte primero.
             *
             * El borrado no espera al botón de guardar y no hay papelera, así que
             * la advertencia es la única oportunidad de arrepentirse. Una fila que
             * nunca se guardó se va sin preguntar.
             */
            async removeSocial(index, saved) {
                if (saved && ! await dialog.confirm({
                    title: @js(__('company.social.remove_confirm.title')),
                    message: @js(__('company.social.remove_confirm.message')),
                    accept: @js(__('company.social.remove_confirm.accept')),
                    type: 'danger',
                })) {
                    return;
                }

                await this.$wire.removeSocialRow(index);
            },

            /**
             * Descarta lo escrito en el paso parado, y solo ese.
             *
             * El estado real vive en el server (los campos van por wire:model
             * contra el DTO), así que revertir es cosa suya; acá solo se baja el
             * bag de errores del front, que sí es local.
             */
            async discard(step) {
                this.errors = {};

                if (step === 'main') {
                    await this.$wire.discardMain();

                    return;
                }

                await this.$wire.discardCommercial();
            },

            /**
             * Valida el paso donde está parado el usuario, y solo ese.
             *
             * El paso llega por parámetro porque `step` vive en el stepper, no
             * acá: el click se evalúa en el scope del pie, que sí lo ve.
             */
            async submit(step) {
                const rules = this.rules[step] ?? {};
                const values = {};

                for (const field in rules) {
                    values[field] = this.$wire.get(`${this.path}.${field}`);
                }

                this.errors = validate(values, rules);

                if (Object.keys(this.errors).length > 0) {
                    return;
                }

                // Una acción por paso: el 1 crea la compañía y abre el 2, el 2
                // actualiza sus columnas y sus redes.
                //
                // El server valida de nuevo lo que el front no puede (`exists`
                // contra la base, el formato de la URL, la red repetida) y pinta
                // esos errores por el ErrorBag.
                if (step !== 'main') {
                    await this.$wire.saveCommercial();

                    return;
                }

                if (await this.$wire.saveMain()) {
                    // Abre el paso 2 y lleva hasta él, la primera vez. Si ya
                    // estaba abierto, el stepper ignora el evento.
                    this.$dispatch('stepper-unlock');
                }
            },
        }));
    </script>
@endscript
