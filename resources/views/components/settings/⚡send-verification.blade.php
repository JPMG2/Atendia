<?php

use App\Livewire\Forms\Settings\EmailForm;
use App\Traits\HasNotifications;
use Livewire\Component;

/**
 * "Verify it now" inside the security checkup: sends the link on the spot
 * and toasts, instead of walking the owner to the email card.
 */
new class extends Component
{
    use HasNotifications;

    public EmailForm $form;

    public function send(): void
    {
        $this->dispatchNotification($this->form->sendVerification());
    }
};
?>

<button type="button" class="st-link" style="font-size: var(--text-xs)" wire:click="send" wire:loading.attr="disabled">
    {{ __('profile.checkup.email_verify_link') }}
</button>
