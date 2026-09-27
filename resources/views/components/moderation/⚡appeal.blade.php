<?php

use App\Livewire\Forms\Moderation\AppealForm;
use App\Traits\HasNotifications;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The appeal door inside the suspension banner: one message per suspension,
 * then the banner says it is waiting on us. The admin reads it on her desk.
 */
new class extends Component
{
    use HasNotifications;

    public AppealForm $form;

    public bool $open = false;

    public function mount(): void
    {
        $this->form->setup();
    }

    #[Computed]
    public function appealedAt(): ?string
    {
        return Auth::user()?->business?->appealed_at?->inBusinessTime()->format('d/m/Y');
    }

    public function send(): void
    {
        $this->dispatchNotification($this->form->save());

        if ($this->getErrorBag()->isEmpty()) {
            $this->open = false;
            unset($this->appealedAt);
        }
    }
};
?>

<div class="mod-appeal">
    @if ($this->appealedAt !== null)
        <p class="mod-appeal-waiting">{{ __('moderation.appeal.waiting', ['date' => $this->appealedAt]) }}</p>
    @elseif (! $open)
        <x-ui.button variant="secondary" size="sm" wire:click="$set('open', true)">
            {{ __('moderation.appeal.open') }}</x-ui.button>
    @else
        <x-catalog.form-row>
            <x-inputsform.textarea
                span="full"
                name="message"
                :rows="3"
                :label="__('moderation.appeal.field')"
                :placeholder="__('moderation.appeal.placeholder')"
                wire:model="form.message"
            >
                {{ $form->message }}</x-inputsform.textarea>
        </x-catalog.form-row>
        <div class="mt-2 flex gap-2">
            <x-ui.button variant="danger" size="sm" wire:click="$set('open', false)">
                {{ __('moderation.appeal.cancel') }}</x-ui.button>
            <x-ui.button variant="primary" size="sm" wire:click="send">
                {{ __('moderation.appeal.send') }}</x-ui.button>
        </div>
    @endif
</div>
