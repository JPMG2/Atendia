<?php

use App\Livewire\Forms\Team\JoinTeamForm;
use App\Enums\NotificationType;
use App\Models\TeamInvitation;
use App\Models\User;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The invitation's landing: a name and a password turn the mail link into a
 * seat. Looked up by the token's hash; an expired or used link, or an
 * address that already has an account, gets its explanation instead.
 */
new class extends Component
{
    public JoinTeamForm $form;

    #[Locked]
    public string $token = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->form->name = (string) $this->invitation()?->name;
    }

    public function invitation(): ?TeamInvitation
    {
        return TeamInvitation::findValid($this->token);
    }

    public function emailTaken(TeamInvitation $invitation): bool
    {
        return User::emailHasAccount($invitation->email);
    }

    /** The plan shrank after the mail left: said on arrival, not after typing a password. */
    public function seatsFull(TeamInvitation $invitation): bool
    {
        return ! $invitation->business->canFillOfferedSeat();
    }

    public function join(): mixed
    {
        $invitation = $this->invitation();

        if ($invitation === null || $this->emailTaken($invitation) || $this->seatsFull($invitation)) {
            return null;
        }

        $notification = $this->form->save($invitation);

        if ($notification->type !== NotificationType::Success) {
            return null;
        }

        session()->flash('status', $notification->message);

        return $this->redirectRoute('conversations', navigate: false);
    }
};
?>

<div>
    @php($invitation = $this->invitation())

    @if ($invitation === null)
        <div class="mb-6">
            <h2 class="text-strong font-display text-3xl font-extrabold tracking-tight">{{ __('team.join.expired_title') }}</h2>
            <p class="text-muted mt-1.5">{{ __('team.join.expired_body') }}</p>
        </div>
        <x-ui.button :href="route('login')" variant="secondary">{{ __('team.join.go_login') }}</x-ui.button>
    @elseif ($this->emailTaken($invitation))
        <div class="mb-6">
            <h2 class="text-strong font-display text-3xl font-extrabold tracking-tight">{{ __('team.join.title', ['business' => $invitation->business->name]) }}</h2>
        </div>
        <x-ui.alert variant="warning" icon="mail" class="mb-6">{{ __('team.join.taken') }}</x-ui.alert>
        <x-ui.button :href="route('login')" variant="secondary">{{ __('team.join.go_login') }}</x-ui.button>
    @elseif ($this->seatsFull($invitation))
        <div class="mb-6">
            <h2 class="text-strong font-display text-3xl font-extrabold tracking-tight">{{ __('team.join.title', ['business' => $invitation->business->name]) }}</h2>
        </div>
        <x-ui.alert variant="warning" icon="users-round" class="mb-6">{{ __('team.join.seats_full', ['business' => $invitation->business->name]) }}</x-ui.alert>
        <x-ui.button :href="route('login')" variant="secondary">{{ __('team.join.go_login') }}</x-ui.button>
    @else
        <div class="mb-8">
            <h2 class="text-strong font-display text-3xl font-extrabold tracking-tight">{{ __('team.join.title', ['business' => $invitation->business->name]) }}</h2>
            <p class="text-muted mt-1.5">{{ __('team.join.sub', ['email' => $invitation->email]) }}</p>
        </div>

        <form wire:submit="join" class="bp-form">
            <x-catalog.form-row>
                <x-inputsform.input span="full" name="name" autocomplete="name" :label="__('team.join.name')" wire:model="form.name" required />
            </x-catalog.form-row>
            <x-catalog.form-row>
                <x-inputsform.input span="full" name="password" type="password" autocomplete="new-password" :label="__('team.join.password')" wire:model="form.password" required />
            </x-catalog.form-row>
            <x-catalog.form-row>
                <x-inputsform.input span="full" name="password_confirmation" type="password" autocomplete="new-password" :label="__('team.join.password_confirmation')" wire:model="form.password_confirmation" required />
            </x-catalog.form-row>

            <x-ui.button type="submit" icon="users-round" :full-width="true">{{ __('team.join.submit') }}</x-ui.button>
        </form>
    @endif
</div>
