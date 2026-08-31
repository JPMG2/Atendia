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
 * Company: AtendIa's own data — a SINGLE record, not the client businesses.
 *
 * Loading goes in STEPS: the second opens only once the company exists,
 * because saving the first is what unlocks it. Step 1 already persists; step 2
 * has no action yet and the social rows are still a mock-up.
 */
new #[Title('Compañía')] class extends Component {
    use HasNotifications;

    public CompanyForm $form;

    /**
     * Whether the company is loaded. This is what opens the second step.
     *
     * `#[Locked]` and set ONCE in `mount()`: it seeds the stepper's `x-data`,
     * so a changing value would make Alpine re-initialize and lose the step the
     * user was on. The live unlock travels on the `stepper-unlock` event.
     */
    #[Locked]
    public bool $isRegistered = false;

    /**
     * How many times step 1 was discarded. Goes in its panel's `wire:key`.
     *
     * The fields use deferred `wire:model`, so on discard the server replays
     * values it often already held: the HTML comes out identical and what was
     * typed survives on screen. Changing the key rebuilds the panel instead.
     */
    #[Locked]
    public int $mainRevision = 0;

    /** The same for the step 2 panel. See {@see self::$mainRevision}. */
    #[Locked]
    public int $commercialRevision = 0;

    /**
     * Leaves the DTO loaded BEFORE the first render.
     *
     * `setup()` is not a Livewire Form hook: without this call the DTO stays
     * null and the first `wire:model` blows up with "Cannot assign array to
     * property".
     */
    public function mount(): void
    {
        $this->form->setup();

        $this->isRegistered = $this->form->recordId !== null;
    }

    /**
     * Saves the main step. Returns whether it saved, which unlocks step 2.
     *
     * `$isRegistered` is NOT touched here: it seeds the stepper's `x-data` and
     * writing it would re-initialize Alpine, losing the step. No `authorize()`
     * either — the route's admin middleware re-applies on every request.
     */
    public function saveMain(): bool
    {
        $notification = $this->form->saveMain();

        $this->dispatchNotification($notification);

        return $notification->type !== NotificationType::Error;
    }

    /**
     * Saves the commercial step (contact and links). Returns whether it saved.
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

    /** Adds a social row right below the given one. */
    public function addSocialRow(int $after): void
    {
        $this->form->addSocialRow($after);
    }

    /**
     * Removes a social row. One already stored is deleted right away.
     *
     * The screen raises the warning BEFORE calling here, through the system
     * dialog; authorization stays the panel's, since the admin middleware
     * re-applies on every Livewire request.
     */
    public function removeSocialRow(int $index): void
    {
        $notification = $this->form->removeSocialRow($index);

        if ($notification !== null) {
            $this->dispatchNotification($notification);
        }
    }

    /**
     * Discards what was typed in the main step and restores what is stored.
     *
     * It clears the server-side errors too: a field left red by an attempt that
     * was just discarded describes nothing of what is on screen now.
     */
    public function discardMain(): void
    {
        $this->form->discardMain();

        $this->resetValidation();

        $this->mainRevision++;
    }

    /**
     * Active countries for the combobox.
     *
     * The catalog prefixes the code ("ARG — Argentina") so it can be searched
     * by; here the country is picked by name, so the screen passes a label of
     * its own instead of dragging along a code nobody will type.
     *
     * @return array<int, array{value: int, label: string}>
     */
    #[Computed]
    public function countryOptions(): array
    {
        return Country::options(states: [true], label: fn(Country $country): string => $country->name);
    }

    /**
     * Provinces of the chosen country.
     *
     * No hook has to reload it: the computed READS `form.data.country_id`, so
     * the next render already carries the new list, and with no country chosen
     * all of them show. No country prefix on the label, unlike the catalog's.
     *
     * @return array<int, array{value: int, label: string}>
     */
    #[Computed]
    public function provinceOptions(): array
    {
        return $this->form->data?->country_id ? Province::options(states: [true], label: fn(Province $province): string => $province->name, countryId: $this->form->data?->country_id) : [];
    }

    /**
     * Active regions for the combobox.
     *
     * @return array<int, array{value: int, label: string}>
     */
    #[Computed]
    public function regionOptions(): array
    {
        return $this->form->data?->province_id ? Region::options(states: [true], provinceId: $this->form->data?->province_id) : [];
    }

    /**
     * Active tax conditions for the combobox.
     *
     * @return array<int, array{value: int, label: string}>
     */
    #[Computed]
    public function taxConditionOptions(): array
    {
        return $this->form->data?->country_id ? TaxCondition::options(states: [true], countryId: $this->form->data?->country_id) : [];
    }

    /**
     * Catalog social networks, to pick from on each row.
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
