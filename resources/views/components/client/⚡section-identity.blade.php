<?php

use App\Actions\Business\WriteBusinessBio;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\Client\IdentityForm;
use App\Traits\HasNotifications;
use Illuminate\Support\Facades\Auth;
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

    /** A proposal is on screen: the offer becomes "write me another one". */
    public bool $proposed = false;

    public function mount(): void
    {
        $this->form->setup();
    }

    #[Computed]
    public function logoUrl(): ?string
    {
        return $this->form->logo_path !== null ? Storage::disk('public')->url($this->form->logo_path) : null;
    }

    /**
     * Fills the field with a proposal and stops there: the owner reads it,
     * edits it and saves. The unsaved pill is what tells her nothing is stored.
     */
    public function writeBio(WriteBusinessBio $writer): void
    {
        $business = Auth::user()?->business;
        $written = $business !== null ? $writer->handle($business, $this->form->name) : null;

        if ($written === null) {
            $this->dispatchNotification(new NotificationDto(
                __('client.business.identity.ai_failed'),
                NotificationType::Error,
            ));

            return;
        }

        $this->form->description = $written;
        $this->proposed = true;

        $this->dispatchNotification(new NotificationDto(
            __('client.business.identity.ai_done'),
            NotificationType::Success,
        ));
    }

    public function save(): void
    {
        $this->dispatchNotification($this->form->save());
    }
};
?>

<x-ui.card class="bp-card" data-section="identidad" x-data="clientIdentityForm">
    <div
        x-data="sectionDirty"
        x-on:input="markDirty()"
        x-on:change="markDirty()"
        x-on:click="trackSave($event)"
        x-on:notify.window="settle($event.detail)"
    >
    <div class="bp-card-head">
        <h2>{{ __('client.business.identity.title') }}</h2>
            <span class="status-tag is-warning" x-show="dirty" x-cloak>{{ __('client.business.unsaved_pill') }}</span>
    </div>
    <p class="bp-card-sub">{{ __('client.business.identity.sub') }}</p>

    {{-- It proposes into the field and never saves, so the owner always has
    the last word on the line her assistant reads to a customer. --}}
    <x-ui.ai-banner
        class="mb-4"
        :title="__('client.business.identity.ai_title')"
        :body="__('client.business.identity.ai_body')"
        :action="__('client.business.identity.ai_action')"
        call="writeBio"
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
            >
                {{-- Where the writing happens, like Gmail and Copilot: asking
                for it should not mean scrolling back up to the banner. --}}
                <x-slot:action>
                    <x-ui.button
                        variant="ghost"
                        size="sm"
                        icon="sparkles"
                        wire:click="writeBio"
                        wire:loading.attr="disabled"
                        data-testid="identity-bio-write"
                    >{{ $proposed ? __('client.business.identity.ai_redo') : __('client.business.identity.ai_action') }}</x-ui.button>
                </x-slot:action>
            </x-inputsform.textarea>
        </x-catalog.form-row>
        <x-catalog.form-row>
            <x-inputsform.file
                span="full"
                name="logo_file"
                :label="__('client.business.identity.logo').' · '.__('client.business.optional')"
                :note="__('client.business.identity.logo_hint')"
                :preview="$this->logoUrl"
                accept="image/png,image/webp,image/jpeg"
                wire:model="form.logo_file"
            />
        </x-catalog.form-row>
    </div>

    <div class="bp-card-actions">
        <x-ui.button x-bind:class="{ 'is-idle': ! dirty }"
            variant="primary"
            size="sm"
            x-on:click="submit()"
        >
            {{ __('client.business.actions.save') }}</x-ui.button>
    </div>
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
