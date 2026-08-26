<?php

use App\Livewire\Forms\Configuration\CompanyForm;
use App\Models\Country;
use App\Models\Province;
use App\Models\Region;
use App\Models\SocialNetwork;
use App\Models\TaxCondition;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Compañía: los datos de AtendIa — UN solo registro, no los negocios clientes.
 *
 * Los campos ya están cableados al `CompanyForm` y su DTO, y el botón de guardar
 * pasa por la validación del front (el `@script` del pie); faltan la validación
 * del server y el guardado. Las redes sociales siguen siendo maqueta: todavía no
 * existe la tabla que las relacione con la compañía.
 */
new #[Title('Compañía')] class extends Component {
    public CompanyForm $form;

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

<div x-data="companyForm">
    <div class="page-head">
        <div>
            <h1 class="page-head-title">{{ __('company.title') }}</h1>
            <p class="page-head-sub">{{ __('company.subtitle') }}</p>
        </div>
    </div>

    <x-ui.tabs default="data" :tabs="[
        ['value' => 'data', 'label' => __('company.tabs.data'), 'icon' => 'building-2'],
        ['value' => 'contact', 'label' => __('company.tabs.contact'), 'icon' => 'at-sign'],
    ]">

        {{-- ============ DATOS DE LA EMPRESA ============
             Orden de carga: quién es → con qué número factura → dónde está →
             con qué se la ve → qué cierra la factura. --}}
        <x-ui.card class="mt-4" x-show="tab === 'data'" x-cloak>

            <div class="config-block">
                <div>
                    <h2 class="config-block-title">{{ __('company.identity.title') }}</h2>
                    <p class="config-block-desc">{{ __('company.identity.desc') }}</p>
                </div>

                <div class="config-fields catalog-form">
                    <x-catalog.form-row>
                        <x-inputsform.input span="text" required name="legal_name" alpine-error="legal_name" :label="__('company.fields.legal_name')"
                            :placeholder="__('company.fields.legal_name_placeholder')" wire:model="form.data.legal_name" />

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

                {{-- De lo general a lo puntual: país → provincia → región, y recién
                     después la calle. El país se elige primero porque de él cuelga
                     todo lo demás, la condición fiscal incluida. --}}
                <div class="config-fields catalog-form">
                    <x-catalog.form-row>
                        <x-inputsform.combobox span="text" required name="country_id" alpine-error="country_id" :label="__('company.fields.country')"
                            :placeholder="__('company.fields.country_placeholder')" :options="$this->countryOptions" :value="$form->data?->country_id"
                            wire:model.live="form.data.country_id" />

                        <x-inputsform.combobox span="text" required name="province_id" alpine-error="province_id" :label="__('company.fields.province')"
                            :placeholder="__('company.fields.province_placeholder')" :options="$this->provinceOptions" :value="$form->data?->province_id" loading="form.data.country_id"
                            wire:model.live="form.data.province_id" />
                    </x-catalog.form-row>

                    <x-catalog.form-row>
                        <x-inputsform.combobox span="text" required name="region_id" alpine-error="region_id" :label="__('company.fields.region')"
                            :placeholder="__('company.fields.region_placeholder')" :options="$this->regionOptions" :value="$form->data?->region_id" loading="form.data.province_id"
                            wire:model="form.data.region_id" />

                        <x-inputsform.input span="long" required name="address" alpine-error="address" :label="__('company.fields.address')" :placeholder="__('company.fields.address_placeholder')"
                            maxlength="255" wire:model="form.data.address" />
                    </x-catalog.form-row>
                </div>
            </div>

            <div class="config-block">
                <div>
                    <h2 class="config-block-title">{{ __('company.tax.title') }}</h2>
                    <p class="config-block-desc">{{ __('company.tax.desc') }}</p>
                </div>

                {{-- La condición va primero: es la que dice qué número corresponde
                     cargar, no al revés. --}}
                <div class="config-fields catalog-form">
                    <x-catalog.form-row>
                        <x-inputsform.combobox span="text" required name="tax_condition_id" alpine-error="tax_condition_id" :label="__('company.fields.tax_condition')"
                            :placeholder="__('company.fields.tax_condition_placeholder')" :options="$this->taxConditionOptions" :value="$form->data?->tax_condition_id"
                            wire:model="form.data.tax_condition_id" />

                        <x-inputsform.input span="long" name="tax_id" class="font-mono" :label="__('company.fields.tax_id')"
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

                            {{-- Sin cablear: la zona de carga todavía no recibe archivos.
                                 El control real va a ser un <x-ui.*> con su test, y
                                 recién ahí se ata a form.data.logo_path_*. --}}
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

        {{-- ============ CONTACTOS Y REDES ============ --}}
        <x-ui.card class="mt-4" x-show="tab === 'contact'" x-cloak>

            <div class="config-block">
                <div>
                    <h2 class="config-block-title">{{ __('company.contact.title') }}</h2>
                    <p class="config-block-desc">{{ __('company.contact.desc') }}</p>
                </div>

                <div class="config-fields catalog-form">
                    {{-- Dos filas, no tres campos apretados: un teléfono en mono
                         necesita su ancho y truncar un dato no es compactar. --}}
                    <x-catalog.form-row>
                        <x-inputsform.input span="text" name="email" type="email" icon="mail"
                            :label="__('company.fields.email')" :placeholder="__('company.fields.email_placeholder')" wire:model="form.data.email" />

                        <x-inputsform.input span="text" name="phone" icon="phone" class="font-mono"
                            :label="__('company.fields.phone')" :placeholder="__('company.fields.phone_placeholder')" maxlength="30" wire:model="form.data.phone" />
                    </x-catalog.form-row>

                    <x-catalog.form-row>
                        <x-inputsform.input span="long" name="web" type="url" icon="globe"
                            :label="__('company.fields.web')" :placeholder="__('company.fields.web_placeholder')" wire:model="form.data.web" />
                    </x-catalog.form-row>
                </div>
            </div>

            {{-- Las redes no son una lista fija: la empresa suma las que tenga y
                 quita las que deje de usar. Cada fila es una red. --}}
            <div class="config-block" x-data="{ rows: [1], next: 2 }">
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

                        {{-- Quitar y agregar viven en la MISMA línea de la fila: un
                             botón suelto abajo rompe la alineación y obliga a bajar
                             la vista para sumar la red siguiente. Siempre queda una
                             fila: si se pudieran borrar todas, no habría dónde
                             volver a empezar. --}}
                        <template x-for="(row, index) in rows" :key="row">
                            <div class="config-social-row">
                                <x-inputsform.combobox span="text" :aria-label="__('company.social.network')" :placeholder="__('company.social.network_placeholder')"
                                    :options="$this->socialOptions" />

                                <x-inputsform.input span="long" icon="link" :aria-label="__('company.social.url')"
                                    :placeholder="__('company.social.url_placeholder')" />

                                <x-ui.icon-button icon="trash-2" variant="ghost" class="config-social-remove"
                                    :label="__('company.social.remove')" x-bind:disabled="rows.length === 1"
                                    x-on:click="rows.splice(index, 1)" />

                                <x-ui.icon-button icon="plus" variant="ghost" class="config-social-add"
                                    :label="__('company.social.add')" data-testid="social-add"
                                    x-on:click="rows.splice(index + 1, 0, next++)" />
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </x-ui.card>
    </x-ui.tabs>

    {{-- Las acciones cierran la pantalla, igual que el pie de los maestros: el
         guardar se busca al final del formulario, no arriba de todo. Va una sola
         vez, fuera de los tabs: es el mismo formulario, no uno por solapa. --}}
    <div class="catalog-form-foot config-foot">
        <span class="catalog-foot-grow"></span>
        <x-ui.button variant="ghost">{{ __('company.discard') }}</x-ui.button>
        <x-ui.button variant="primary" icon="check" x-on:click="submit()">{{ __('company.save') }}</x-ui.button>
    </div>
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

            rules: {
                legal_name: ['required', ['maxLength', 255], 'noMarkup'],
                country_id: ['required'],
                province_id: ['required'],
                region_id: ['required'],
                address: ['required', ['maxLength', 255], 'noMarkup'],
                tax_condition_id: ['required'],
            },

            submit() {
                const values = {};

                for (const field in this.rules) {
                    values[field] = this.$wire.get(`${this.path}.${field}`);
                }

                this.errors = validate(values, this.rules);

                if (Object.keys(this.errors).length > 0) {
                    return;
                }

                // Acá se engancha el guardado del server el día que la acción
                // exista: la pantalla todavía no persiste nada.
            },
        }));
    </script>
@endscript
