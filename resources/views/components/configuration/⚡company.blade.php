<?php

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
 * MAQUETA. La pantalla todavía no carga ni guarda nada: lo único que consulta
 * son las opciones de los combobox, porque un desplegable vacío no se puede
 * mirar. El cableado (Form, validación, persistencia) viene después.
 */
new #[Title('Compañía')] class extends Component
{
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
        return Country::options(states: [true], label: fn (Country $country): string => $country->name);
    }

    /**
     * Provincias activas para el combobox.
     *
     * Mismo criterio que el país: el catálogo cuelga el código del país para
     * distinguir dos provincias homónimas, y acá alcanza con el nombre porque
     * el país ya quedó elegido en el campo de arriba.
     *
     * @return array<int, array{value: int, label: string}>
     */
    #[Computed]
    public function provinceOptions(): array
    {
        return Province::options(states: [true], label: fn (Province $province): string => $province->name);
    }

    /**
     * Regiones activas para el combobox.
     *
     * @return array<int, array{value: int, label: string}>
     */
    #[Computed]
    public function regionOptions(): array
    {
        return Region::options(states: [true]);
    }

    /**
     * Condiciones fiscales activas para el combobox.
     *
     * @return array<int, array{value: int, label: string}>
     */
    #[Computed]
    public function taxConditionOptions(): array
    {
        return TaxCondition::options(states: [true]);
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

<div>
    <div class="page-head">
        <div>
            <h1 class="page-head-title">{{ __('company.title') }}</h1>
            <p class="page-head-sub">{{ __('company.subtitle') }}</p>
        </div>
    </div>

    {{-- Las acciones acompañan el scroll: el formulario es largo y volver arriba
         para guardar es lo que hace que la gente no guarde. --}}
    <div class="config-actions">
        <x-ui.button variant="ghost">{{ __('company.discard') }}</x-ui.button>
        <x-ui.button variant="primary" icon="check">{{ __('company.save') }}</x-ui.button>
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
                        <x-inputsform.input span="text" name="legal_name" :label="__('company.fields.legal_name')"
                            :placeholder="__('company.fields.legal_name_placeholder')" />

                        <x-inputsform.input span="long" name="tagline" :label="__('company.fields.tagline')"
                            :placeholder="__('company.fields.tagline_placeholder')"
                            :hint="__('company.fields.tagline_hint')" />
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
                        <x-inputsform.combobox span="text" name="country_id" :label="__('company.fields.country')"
                            :placeholder="__('company.fields.country_placeholder')"
                            :options="$this->countryOptions" />

                        <x-inputsform.combobox span="text" name="province_id" :label="__('company.fields.province')"
                            :placeholder="__('company.fields.province_placeholder')"
                            :options="$this->provinceOptions" />
                    </x-catalog.form-row>

                    <x-catalog.form-row>
                        <x-inputsform.combobox span="text" name="region_id" :label="__('company.fields.region')"
                            :placeholder="__('company.fields.region_placeholder')"
                            :options="$this->regionOptions" />

                        <x-inputsform.input span="long" name="address" :label="__('company.fields.address')"
                            :placeholder="__('company.fields.address_placeholder')" maxlength="255" />
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
                        <x-inputsform.combobox span="text" name="tax_condition_id"
                            :label="__('company.fields.tax_condition')"
                            :placeholder="__('company.fields.tax_condition_placeholder')"
                            :options="$this->taxConditionOptions" />

                        <x-inputsform.input span="long" name="tax_id" class="font-mono"
                            :label="__('company.fields.tax_id')" :hint="__('company.fields.tax_id_hint')"
                            maxlength="20" />
                    </x-catalog.form-row>
                </div>
            </div>


            <div class="config-block">
                <div>
                    <h2 class="config-block-title">{{ __('company.logo.title') }}</h2>
                    <p class="config-block-desc">{{ __('company.logo.desc') }}</p>
                </div>

                <div class="config-logo-grid">
                    @foreach ([
                        ['name' => 'logo_path_light', 'label' => __('company.logo.light')],
                        ['name' => 'logo_path_dark', 'label' => __('company.logo.dark')],
                    ] as $logo)
                        <div class="field">
                            <x-ui.label :for="'if-'.$logo['name']">{{ $logo['label'] }}</x-ui.label>

                            {{-- Maqueta: la zona de carga todavía no recibe archivos.
                                 El control real va a ser un <x-ui.*> con su test. --}}
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
                        <x-inputsform.input span="full" name="text_copyright"
                            :label="__('company.fields.copyright')"
                            :placeholder="__('company.fields.copyright_placeholder')" maxlength="255" />
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
                            :label="__('company.fields.email')"
                            :placeholder="__('company.fields.email_placeholder')" />

                        <x-inputsform.input span="text" name="phone" icon="phone" class="font-mono"
                            :label="__('company.fields.phone')"
                            :placeholder="__('company.fields.phone_placeholder')" maxlength="30" />
                    </x-catalog.form-row>

                    <x-catalog.form-row>
                        <x-inputsform.input span="long" name="web" type="url" icon="globe"
                            :label="__('company.fields.web')"
                            :placeholder="__('company.fields.web_placeholder')" />
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
                                <x-inputsform.combobox span="text" :aria-label="__('company.social.network')"
                                    :placeholder="__('company.social.network_placeholder')"
                                    :options="$this->socialOptions" />

                                <x-inputsform.input span="long" icon="link"
                                    :aria-label="__('company.social.url')"
                                    :placeholder="__('company.social.url_placeholder')" />

                                <x-ui.icon-button icon="trash-2" variant="ghost"
                                    class="config-social-remove" :label="__('company.social.remove')"
                                    x-bind:disabled="rows.length === 1"
                                    x-on:click="rows.splice(index, 1)" />

                                <x-ui.icon-button icon="plus" variant="ghost"
                                    class="config-social-add" :label="__('company.social.add')"
                                    data-testid="social-add"
                                    x-on:click="rows.splice(index + 1, 0, next++)" />
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </x-ui.card>
    </x-ui.tabs>
</div>
