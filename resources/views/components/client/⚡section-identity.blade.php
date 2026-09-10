<?php

use App\Livewire\Forms\Client\IdentityForm;
use App\Traits\HasNotifications;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Identity card of "Mi negocio". The state, validation and save live in
 * {@see IdentityForm}; the component only wires the screen to it and turns
 * the stored logo path into a previewable URL.
 */
new class extends Component
{
    use HasNotifications;
    use WithFileUploads;

    public IdentityForm $form;

    public function mount(): void
    {
        $this->form->setup();
    }

    #[Computed]
    public function logoUrl(): ?string
    {
        return $this->form->logo_path !== null ? Storage::disk('public')->url($this->form->logo_path) : null;
    }

    public function save(): void
    {
        $this->dispatchNotification($this->form->save());
    }
};
?>

<x-ui.card class="bp-card" data-section="identidad" x-data="clientIdentityForm">
    <div class="bp-card-head">
        <h2>{{ __('client.business.identity.title') }}</h2>
    </div>
    <p class="bp-card-sub">{{ __('client.business.identity.sub') }}</p>

    {{-- The assistant offers to write the bio; the fields below stay the
    manual path — an offer, never a gate. --}}
    <x-ui.ai-banner
        class="mb-4"
        :title="__('client.business.identity.ai_title')"
        :body="__('client.business.identity.ai_body')"
        :action="__('client.business.identity.ai_action')"
    />

    <div class="bp-form">
        <x-catalog.form-row>
            <x-inputsform.input
                span="full"
                :label="__('client.business.identity.name')"
                :hint="__('client.business.identity.name_hint')"
                name="name"
                alpine-error="name"
                wire:model="form.name"
            />
        </x-catalog.form-row>
        <x-catalog.form-row>
            <x-inputsform.textarea
                span="full"
                :label="__('client.business.identity.description')"
                name="description"
                :hint="__('client.business.identity.description_hint')"
                :rows="3"
                maxlength="500"
                counter
                alpine-error="description"
                wire:model="form.description"
            />
        </x-catalog.form-row>
        <x-catalog.form-row>
            <x-inputsform.file
                span="full"
                name="logo_file"
                :label="__('client.business.identity.logo').' · '.__('client.business.optional')"
                :note="__('client.business.identity.logo_hint')"
                :preview="$this->logoUrl"
                wire:model="form.logo_file"
            />
        </x-catalog.form-row>
    </div>

    <div class="bp-card-actions">
        <x-ui.button
            variant="primary"
            size="sm"
            x-on:click="submit()"
        >
            {{ __('client.business.actions.save') }}</x-ui.button>
    </div>
</x-ui.card>

@script
    <script>
        /*
         * FRONT validation of the identity card, same criterion as the company
         * screen: the rules mirror the server's replicable half. The logo is a
         * file, so its mime and size still bounce from the server.
         */
        Alpine.data('clientIdentityForm', () => ({
            errors: {},

            // Where the form lives on the server: values are read from there,
            // since wire:model is the card's only state.
            path: 'form',

            rules: {
                name: ['required', ['minLength', 3], ['maxLength', 255], 'noMarkup'],
                description: [['maxLength', 500]],
            },

            async submit() {
                const values = {};

                for (const field in this.rules) {
                    values[field] = this.$wire.get(`${this.path}.${field}`);
                }

                this.errors = validate(values, this.rules);

                if (Object.keys(this.errors).length > 0) {
                    return;
                }

                await this.$wire.save();
            },
        }));
    </script>
@endscript
