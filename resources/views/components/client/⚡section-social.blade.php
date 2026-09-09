<?php

use App\Livewire\Forms\Client\SocialForm;
use App\Models\SocialNetwork;
use App\Traits\HasNotifications;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Social networks card of "Mi negocio". The rows, validation and save live
 * in {@see SocialForm}; the component only wires the screen to it and feeds
 * the network combobox.
 */
new class extends Component
{
    use HasNotifications;

    public SocialForm $form;

    public function mount(): void
    {
        $this->form->setup();
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    #[Computed]
    public function socialOptions(): array
    {
        return SocialNetwork::options(states: [true]);
    }

    public function addSocialRow(int $after): void
    {
        $this->form->addSocialRow($after);
    }

    public function removeSocialRow(int $index): void
    {
        $this->form->removeSocialRow($index);
    }

    public function save(): void
    {
        $this->dispatchNotification($this->form->save());
    }
};
?>

<x-ui.card class="bp-card" data-section="redes">
    <div class="bp-card-head">
        <h2>{{ __('client.business.social.title') }}</h2>
    </div>
    <p class="bp-card-sub">{{ __('client.business.social.sub') }}</p>

    <div class="bp-form">
        <div class="config-social">
            <div class="config-social-row config-social-head">
                <span class="f-short field-label">{{ __('client.business.social.network') }}</span>
                <span class="f-long field-label">{{ __('client.business.social.url') }}</span>
                <span class="config-social-spacer" aria-hidden="true"></span>
            </div>

            {{-- The rows live on the SERVER, like the company's: a row that only
            exists in the browser cannot be saved. Remove and add share the
            line so the eye never leaves the row. --}}
            @foreach ($form->social as $index => $row)
                <div class="config-social-row" wire:key="social-{{ $row['key'] }}">
                    <x-inputsform.combobox
                        span="short"
                        :id="'bp-social-'.$index.'-network'"
                        :name="'social.'.$index.'.social_network_id'"
                        :aria-label="__('client.business.social.network')"
                        :placeholder="__('client.business.social.network_placeholder')"
                        :options="$this->socialOptions"
                        :value="$row['social_network_id']"
                        wire:model="form.social.{{ $index }}.social_network_id"
                    />

                    <x-inputsform.input
                        span="long"
                        icon="link"
                        :id="'bp-social-'.$index.'-url'"
                        :name="'social.'.$index.'.url'"
                        :aria-label="__('client.business.social.url')"
                        :placeholder="__('client.business.social.url_placeholder')"
                        maxlength="255"
                        wire:model="form.social.{{ $index }}.url"
                    />

                    <x-ui.icon-button
                        icon="trash-2"
                        variant="ghost"
                        class="config-social-remove"
                        data-testid="social-remove"
                        :label="__('client.business.social.remove')"
                        :disabled="count($form->social) === 1 && $row['id'] === null"
                        wire:click="removeSocialRow({{ $index }})"
                    />

                    <x-ui.icon-button
                        icon="plus"
                        variant="ghost"
                        class="config-social-add"
                        :label="__('client.business.social.add')"
                        data-testid="social-add"
                        wire:click="addSocialRow({{ $index }})"
                    />
                </div>
            @endforeach
        </div>
    </div>

    <div class="bp-card-actions">
        <x-ui.button
            variant="primary"
            size="sm"
            wire:click="save"
        >
            {{ __('client.business.actions.save') }}</x-ui.button>
    </div>
</x-ui.card>
