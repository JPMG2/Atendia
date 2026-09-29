<?php

use App\Classes\Main\Client;
use App\Livewire\Forms\Client\AppointmentsForm;
use App\Traits\HasNotifications;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Bookings card of "Mi negocio". The state, validation and save live in
 * {@see AppointmentsForm}; the component only wires the screen to it.
 */
new class extends Component
{
    use HasNotifications;

    public AppointmentsForm $form;

    public function mount(): void
    {
        $this->form->setup();
    }

    /** Hours first: without a schedule there are no free slots to offer. */
    #[Computed]
    public function hasHours(): bool
    {
        return Client::for(Auth::user())->schedule?->isComplete ?? false;
    }

    /** The public link she pastes in Instagram or her WhatsApp profile. */
    #[Computed]
    public function bookingLink(): string
    {
        return Auth::user()?->business?->bookingLink() ?? '';
    }

    /** How many services take a booking, so the card names what is still missing. */
    #[Computed]
    public function bookableCount(): int
    {
        return Client::for(Auth::user())->agenda?->bookableServices->count() ?? 0;
    }

    public function save(): void
    {
        $this->dispatchNotification($this->form->save());
    }
};
?>

<x-ui.card class="bp-card" data-section="turnos">
    <div
        x-data="sectionDirty"
        x-on:input="markDirty()"
        x-on:change="markDirty()"
        x-on:click="trackSave($event)"
        x-on:notify.window="settle($event.detail)"
    >
        <div class="bp-card-head">
            <h2>{{ __('client.business.appointments.title') }}</h2>
            <span class="status-tag is-warning" x-show="dirty" x-cloak>{{ __('client.business.unsaved_pill') }}</span>
            @if ($form->appointments_enabled)
                <span class="bp-head-action">
                    <x-ui.button variant="secondary" size="sm" icon="calendar-check" :href="route('agenda')" wire:navigate>
                        {{ __('client.business.appointments.open') }}
                    </x-ui.button>
                </span>
            @endif
        </div>
        <p class="bp-card-sub">{{ __('client.business.appointments.sub') }}</p>

        <x-catalog.form-row>
            <x-inputsform.switch-field
                span="text"
                name="appointments_enabled"
                :label="__('client.business.appointments.enabled')"
                :on="__('client.business.appointments.on')"
                :off="__('client.business.appointments.off')"
                wire:model.live="form.appointments_enabled"
            />
            <x-inputsform.input
                span="short"
                type="number"
                min="1"
                max="50"
                class="font-mono"
                name="appointment_capacity"
                :label="__('client.business.appointments.capacity')"
                :hint="__('client.business.appointments.capacity_hint')"
                wire:model="form.appointment_capacity"
            />
        </x-catalog.form-row>

        {{-- Two rows and not four squeezed fields: this card lives in the
        narrow column, and each row still reaches the right edge. --}}
        <x-catalog.form-row>
            <x-inputsform.input
                span="short"
                type="number"
                min="5"
                max="480"
                class="font-mono"
                name="appointment_slot_minutes"
                :label="__('client.business.appointments.slot_minutes')"
                :hint="__('client.business.appointments.slot_minutes_hint')"
                wire:model="form.appointment_slot_minutes"
            />
            <x-inputsform.input
                span="text"
                type="number"
                min="1"
                max="500"
                class="font-mono"
                name="appointments_per_day"
                :label="__('client.business.appointments.per_day')"
                :hint="__('client.business.appointments.per_day_hint')"
                wire:model="form.appointments_per_day"
            />
        </x-catalog.form-row>

        @if ($form->appointments_enabled)
            {{-- The link is the point of having an agenda: it goes where she
            can copy it, not buried in a help page. --}}
            <x-catalog.form-row>
                <x-inputsform.input
                    span="long"
                    name="booking_link"
                    readonly
                    :label="__('agenda.public.link_label')"
                    :hint="__('agenda.public.link_hint')"
                    :value="$this->bookingLink"
                    class="font-mono"
                />
                <div class="flex flex-none items-center gap-3 self-center">
                    <x-ui.button
                        variant="secondary"
                        size="sm"
                        icon="copy"
                        x-data
                        x-on:click="navigator.clipboard.writeText({{ Js::from($this->bookingLink) }})
                            .then(() => dialog.notify({
                                title: {{ Js::from(__('agenda.public.link_copied')) }},
                                message: {{ Js::from($this->bookingLink) }},
                                type: 'success',
                            }))"
                    >
                        {{ __('agenda.public.link_copy') }}
                    </x-ui.button>
                </div>
            </x-catalog.form-row>
        @endif

        <div class="bp-card-actions">
            @if ($form->appointments_enabled)
                {{-- With the agenda on, the card says which piece is still missing. --}}
                <span class="bp-card-note">
                    <x-icon name="info" :size="14" />
                    @if (! $this->hasHours)
                        {{ __('client.business.appointments.needs_hours') }}
                    @elseif ($this->bookableCount === 0)
                        {{ __('client.business.appointments.needs_services') }}
                    @else
                        {{ trans_choice('client.business.appointments.ready', $this->bookableCount, ['count' => $this->bookableCount]) }}
                    @endif
                </span>
            @endif
            <x-ui.button x-bind:class="{ 'is-idle': ! dirty }" variant="primary" size="sm" wire:click="save">
                {{ __('client.business.actions.save') }}
            </x-ui.button>
        </div>
    </div>
</x-ui.card>
