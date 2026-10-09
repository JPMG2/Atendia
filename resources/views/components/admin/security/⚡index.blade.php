<?php

use App\Classes\Main\StaffTwoFactor;
use App\Livewire\Forms\Admin\StaffPhoneForm;
use App\Models\Company;
use App\Models\Country;
use App\Traits\HasNotifications;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Where a person of the team turns on their own second step.
 *
 * It is also the one page the admin panel leaves open once the plazo ran out,
 * so it has to say why the rest is closed and what is left to do.
 */
new class extends Component
{
    use HasNotifications;

    public StaffPhoneForm $form;

    public function mount(): void
    {
        $this->form->setup();
    }

    public function savePhone(): void
    {
        $this->dispatchNotification($this->form->save());
        unset($this->rule);

        // The card below was drawn without a number: it has to read the new one.
        $this->dispatch('staff-phone-saved');
    }

    /** The card below turned the second step on or off: the notice above has to follow. */
    #[On('two-factor-changed')]
    public function refreshRule(): void
    {
        unset($this->rule);
    }

    #[Computed]
    public function rule(): StaffTwoFactor
    {
        return new StaffTwoFactor(Auth::user()->refresh());
    }

    /** The staff has no business to take the number from: theirs is typed here. */
    #[Computed]
    public function needsOwnPhone(): bool
    {
        return Auth::user()->business_id === null;
    }

    /** @return list<array{code: string, flag: string, name: string}> */
    #[Computed]
    public function phoneCountries(): array
    {
        return Country::phoneFlags(states: [true]);
    }

    /** The team works from where the platform is: its own country preselects the dial code. */
    #[Computed]
    public function defaultDial(): ?string
    {
        return Country::dialCode(Company::current()?->region?->province?->country_id);
    }

    /** The tab is copy: a PHP attribute cannot call __(). */
    public function render(): View
    {
        return $this->view()->title(__('security.staff.title'));
    }
};
?>

<div>
    <x-ui.page-head :title="__('security.staff.title')" :sub="__('security.staff.sub')" />

    @if ($this->rule->overdue)
        <x-ui.alert variant="warning" icon="lock" class="mb-3"
            :title="auth()->user()->secondStepResetAt() !== null ? __('security.staff.reset_title') : __('security.staff.overdue_title')">
            {{ auth()->user()->secondStepResetAt() !== null ? __('security.staff.reset_body') : __('security.staff.overdue_body') }}
        </x-ui.alert>
    @endif

    @if ($this->needsOwnPhone)
        <x-ui.card class="bp-card mb-3">
            <div class="bp-card-head"><h2>{{ __('security.staff.phone.title') }}</h2></div>
            <p class="bp-card-sub">{{ __('security.staff.phone.hint') }}</p>

            <form wire:submit="savePhone">
                <x-catalog.form-row>
                    <x-inputsform.phone
                        span="long"
                        name="whatsapp"
                        :countries="$this->phoneCountries"
                        :default-dial="$this->defaultDial"
                        :value="$form->whatsapp !== '' ? '+'.ltrim($form->whatsapp, '+') : null"
                        wire:model="form.whatsapp"
                        :label="__('security.staff.phone.field')"
                    />
                </x-catalog.form-row>

                <div class="bp-card-actions">
                    <x-ui.button type="submit" variant="primary" size="sm" icon="check">{{ __('security.staff.phone.save') }}</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    @endif

    <livewire:settings.section-two-factor />
</div>
