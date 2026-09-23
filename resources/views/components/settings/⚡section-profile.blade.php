<?php

use App\Livewire\Forms\Settings\ProfileForm;
use App\Traits\HasNotifications;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Profile card of "Ajustes". The state, validation and save live in
 * {@see ProfileForm}; the component only wires the screen to it.
 */
new class extends Component
{
    use HasNotifications;
    use WithFileUploads;

    public ProfileForm $form;

    public function mount(): void
    {
        $this->form->setup();
    }

    #[Computed]
    public function avatarUrl(): ?string
    {
        return Auth::user()->avatarUrl();
    }

    public function save(): void
    {
        $this->dispatchNotification($this->form->save());
        unset($this->avatarUrl);
    }

    public function removeAvatar(): void
    {
        $this->dispatchNotification($this->form->removeAvatar());
        unset($this->avatarUrl);
    }
};
?>

<x-ui.card id="perfil" class="bp-card" x-data="settingsProfileForm" x-on:file-remove="removeAvatar()">
    <div
        x-data="sectionDirty"
        x-on:input="markDirty()"
        x-on:change="markDirty()"
        x-on:click="trackSave($event)"
        x-on:notify.window="settle($event.detail)"
    >
        <div class="bp-card-head">
            <h2>{{ __('settings.profile.title') }}</h2>
            <span class="status-tag is-warning" x-show="dirty" x-cloak>{{ __('settings.unsaved_pill') }}</span>
        </div>
        <p class="bp-card-sub">{{ __('settings.profile.sub') }}</p>

        <x-catalog.form-row>
            <x-inputsform.input
                span="long"
                name="name"
                alpine-error="name"
                wire:model="form.name"
                :label="__('settings.profile.name')"
                autocomplete="name"
                required
            />
            <x-inputsform.input
                span="short"
                name="member_since"
                class="font-mono"
                :label="__('settings.profile.member_since')"
                :value="auth()->user()->created_at?->format('d/m/Y')"
                readonly
            />
        </x-catalog.form-row>

        <x-catalog.form-row>
            <x-inputsform.avatar
                name="avatar_file"
                model="form.avatar_file"
                :label="__('settings.profile.photo')"
                :note="__('settings.profile.photo_hint')"
                :preview="$this->avatarUrl"
                :initials="mb_strtoupper(collect(explode(' ', trim($form->name)))->filter()->take(2)->map(fn ($word) => mb_substr($word, 0, 1))->implode(''))"
                removable
            />
        </x-catalog.form-row>

        <div class="bp-card-actions">
            <x-ui.button x-bind:class="{ 'is-idle': ! dirty }" variant="primary" size="sm" x-on:click="submit()">
                {{ __('settings.save') }}</x-ui.button>
        </div>
    </div>
</x-ui.card>

@script
    <script>
        // FRONT half of ProfileForm's rules: a bad name never costs a request.
        Alpine.data('settingsProfileForm', () => ({
            errors: {},
            path: 'form',
            rules: {
                name: ['required', ['minLength', 2], ['maxLength', 255], 'noMarkup'],
            },

            async submit() {
                const values = { name: this.$wire.get(`${this.path}.name`) ?? '' };

                this.errors = validate(values, this.rules);

                if (Object.keys(this.errors).length > 0) {
                    return;
                }

                await this.$wire.save();
            },

            async removeAvatar() {
                if (
                    !(await dialog.confirm({
                        title: @js(__('settings.profile.photo_remove_title')),
                        message: @js(__('settings.profile.photo_remove_message')),
                        accept: @js(__('settings.profile.photo_remove_accept')),
                        type: 'warning',
                    }))
                ) {
                    return;
                }

                await this.$wire.removeAvatar();

                this.$dispatch('file-reset', { name: 'avatar_file' });
            },
        }));
    </script>
@endscript
