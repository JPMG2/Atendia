<?php

use App\Enums\NotificationType;
use App\Livewire\Forms\Client\ClosuresForm;
use App\Livewire\Forms\Client\HoursForm;
use App\Models\BusinessClosure;
use App\Traits\HasNotifications;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Opening-hours card of "Mi negocio". The week state, validation and save
 * live in {@see HoursForm}; the component only wires the screen to it.
 */
new class extends Component
{
    use HasNotifications;

    public HoursForm $form;

    public ClosuresForm $closures;

    public function mount(): void
    {
        $this->form->setup();
    }

    /**
     * The closures still ahead. Read through the Form, which owns the query:
     * a Blade never builds one.
     *
     * @return Collection<int, BusinessClosure>
     */
    #[Computed]
    public function upcomingClosures(): Collection
    {
        return $this->closures->upcoming();
    }

    public function addClosure(): void
    {
        $notification = $this->closures->add();

        $this->dispatchNotification($notification);

        unset($this->upcomingClosures);

        // The picker sits behind wire:ignore: emptying the model is not
        // enough, it has to be told, or the date she just used stays painted.
        if ($notification->type === NotificationType::Success) {
            $this->dispatch('datepicker-reset', name: 'range');
        }
    }

    /** The country's holidays, offered only when there are any to offer. */
    #[Computed]
    public function holidaysAhead(): int
    {
        $countryId = Auth::user()?->business?->country_id;

        return $countryId === null ? 0 : $this->closures->holidaysAhead($countryId)->count();
    }

    public function importHolidays(): void
    {
        $this->dispatchNotification($this->closures->importCountryHolidays());

        unset($this->upcomingClosures, $this->holidaysAhead);
    }

    public function pendingFor(BusinessClosure $closure): int
    {
        return $this->closures->pendingFor($closure);
    }

    public function notifyClosure(int $id): void
    {
        $this->dispatchNotification($this->closures->notify($id));

        unset($this->upcomingClosures);
    }

    public function removeClosure(int $id): void
    {
        $this->dispatchNotification($this->closures->remove($id));

        unset($this->upcomingClosures);
    }

    public function toggleDay(int $day): void
    {
        $this->form->toggleDay($day);
    }

    public function addShift(int $day): void
    {
        $this->form->addShift($day);
    }

    public function removeShift(int $day, int $index): void
    {
        $this->form->removeShift($day, $index);
    }

    public function applyWeekdays(): void
    {
        $this->form->applyWeekdays();
    }

    public function save(): void
    {
        $this->dispatchNotification($this->form->save());
    }
};
?>

<x-ui.card class="bp-card" data-section="horarios">
    <div
        x-data="sectionDirty"
        x-on:input="markDirty()"
        x-on:change="markDirty()"
        x-on:click="trackSave($event)"
        x-on:notify.window="settle($event.detail)"
    >
    @php
        $days = App\Models\BusinessHour::dayNames();
    @endphp

    <div class="bp-card-head">
        <h2>{{ __('client.business.hours.title') }}</h2>
            <span class="status-tag is-warning" x-show="dirty" x-cloak>{{ __('client.business.unsaved_pill') }}</span>
        <span class="bp-chip"><x-icon name="star" :size="12" /> {{ __('client.business.recommended') }}</span>
        <span class="bp-head-action">
            <x-ui.button
                variant="secondary"
                size="sm"
                wire:click="applyWeekdays"
            >
                {{ __('client.business.hours.apply_weekdays') }}</x-ui.button>
        </span>
    </div>
    <p class="bp-card-sub">{{ __('client.business.hours.sub') }}</p>
    {{-- Said out loud because the capability is invisible otherwise: nobody
    types 02:00 into a "closes" box that used to refuse it. --}}
    <p class="bp-card-sub">{{ __('client.business.hours.overnight_hint') }}</p>

    <div class="bp-hours">
        @foreach ([1, 2, 3, 4, 5, 6, 0] as $day)
            <div wire:key="day-{{ $day }}" @class(['bp-hrow', 'is-closed' => $form->week[$day] === []])>
                <span class="bp-hday">{{ $days[$day] }}</span>
                <x-ui.switch
                    name="day-{{ $day }}"
                    size="sm"
                    :checked="$form->week[$day] !== []"
                    :aria-label="$days[$day]"
                    wire:click="toggleDay({{ $day }})"
                />

                <div class="bp-hshifts">
                    @forelse ($form->week[$day] as $index => $shift)
                        <span class="bp-shift-edit" wire:key="shift-{{ $day }}-{{ $index }}">
                            {{-- Plain text and not a native time input: that picker
                            clips the minutes at this width; the server validates H:i. --}}
                            <x-inputsform.input
                                type="text"
                                size="s"
                                maxlength="5"
                                inputmode="numeric"
                                :id="'bp-h-'.$day.'-'.$index.'-opens'"
                                :placeholder="__('client.business.hours.time_placeholder')"
                                :name="'week.'.$day.'.'.$index.'.opens_at'"
                                :aria-label="$days[$day].' · '.__('client.business.hours.opens')"
                                class="font-mono"
                                wire:model="form.week.{{ $day }}.{{ $index }}.opens_at"
                            />
                            <span class="bp-shift-sep" aria-hidden="true">–</span>
                            <x-inputsform.input
                                type="text"
                                size="s"
                                maxlength="5"
                                inputmode="numeric"
                                :id="'bp-h-'.$day.'-'.$index.'-closes'"
                                :placeholder="__('client.business.hours.time_placeholder')"
                                :name="'week.'.$day.'.'.$index.'.closes_at'"
                                :aria-label="$days[$day].' · '.__('client.business.hours.closes')"
                                class="font-mono"
                                wire:model="form.week.{{ $day }}.{{ $index }}.closes_at"
                            />
                            <x-ui.icon-button
                                icon="trash-2"
                                variant="ghost"
                                class="bp-shift-remove"
                                data-testid="shift-remove"
                                :label="__('client.business.hours.remove_shift')"
                                wire:click="removeShift({{ $day }}, {{ $index }})"
                            />
                        </span>
                    @empty
                        <span class="bp-closed">{{ __('client.business.hours.closed') }}</span>
                    @endforelse
                </div>

                @if ($form->week[$day] !== [])
                    <button type="button" class="bp-hadd" wire:click="addShift({{ $day }})">
                        + {{ __('client.business.hours.add_shift') }}
                    </button>
                @endif
            </div>
        @endforeach
    </div>

    {{-- The days she does NOT open, on top of the weekly grid above: without
    them a holiday on a Tuesday was answered as a normal Tuesday. --}}
    <div class="bp-closures">
        <h3 class="bp-closures-title">{{ __('client.business.hours.closures_title') }}</h3>
        <p class="bp-card-sub">{{ __('client.business.hours.closures_sub') }}</p>

        {{-- Only when the country has holidays loaded and some are still
        ahead: a button that would add nothing is a button that lies. --}}
        @if ($this->holidaysAhead > 0)
            <div class="bp-holidays">
                <x-ui.button variant="secondary" size="sm" wire:click="importHolidays">
                    {{ trans_choice('client.business.hours.holidays_import', $this->holidaysAhead, ['count' => $this->holidaysAhead]) }}</x-ui.button>
                <span class="bp-card-sub">{{ __('client.business.hours.holidays_note') }}</span>
            </div>
        @endif

        @forelse ($this->upcomingClosures as $closure)
            <div class="bp-closure" wire:key="closure-{{ $closure->id }}">
                <span class="bp-closure-days font-mono">{{ $closure->label() }}</span>
                <span class="bp-closure-reason">
                    @if ($closure->isFullDay())
                        {{ $closure->reason }}
                    @else
                        <span class="font-mono">{{ $closure->hoursLabel() }}</span>&nbsp;{{ $closure->reason }}
                    @endif
                </span>
                @php($booked = $this->pendingFor($closure))
                {{-- Somebody is holding an hour on a day that is not going to
                happen: finding out at the closed door is the one outcome
                nobody can be left with. --}}
                @if ($booked > 0)
                    <span class="status-tag is-warning">{{ trans_choice('client.business.hours.closure_booked', $booked, ['count' => $booked]) }}</span>
                    <x-ui.button variant="secondary" size="sm" wire:click="notifyClosure({{ $closure->id }})">
                        {{ __('client.business.hours.closure_notify') }}</x-ui.button>
                @endif
                <x-ui.icon-button
                    icon="trash-2"
                    variant="ghost"
                    class="bp-shift-remove"
                    data-testid="closure-remove"
                    :label="__('client.business.hours.closure_remove')"
                    wire:click="removeClosure({{ $closure->id }})"
                />
            </div>
        @empty
            <p class="bp-closed">{{ __('client.business.hours.closure_empty') }}</p>
        @endforelse

        <x-catalog.form-row>
            <x-inputsform.datepicker
                span="text"
                mode="range"
                name="range"
                :label="__('client.business.hours.closure_dates')"
                :hint="__('client.business.hours.closure_dates_hint')"
                :value="$closures->range"
                wire:model="closures.range"
            />
            <x-inputsform.input
                span="text"
                name="reason"
                :label="__('client.business.hours.closure_reason')"
                :hint="__('client.business.hours.closure_reason_hint')"
                maxlength="80"
                wire:model="closures.reason"
            />
            {{-- Empty = the door stays shut, which is the common case. Filled,
            the day opens on these instead of its weekly hours. --}}
            <x-inputsform.input
                span="code"
                type="text"
                maxlength="5"
                inputmode="numeric"
                name="opens_at"
                :label="__('client.business.hours.opens')"
                :placeholder="__('client.business.hours.time_placeholder')"
                class="font-mono"
                wire:model="closures.opens_at"
            />
            <x-inputsform.input
                span="code"
                type="text"
                maxlength="5"
                inputmode="numeric"
                name="closes_at"
                :label="__('client.business.hours.closes')"
                :hint="__('client.business.hours.closure_hours_hint')"
                :placeholder="__('client.business.hours.time_placeholder')"
                class="font-mono"
                wire:model="closures.closes_at"
            />
            <x-ui.button variant="secondary" size="sm" wire:click="addClosure">
                {{ __('client.business.hours.closure_add') }}</x-ui.button>
        </x-catalog.form-row>
    </div>

    <div class="bp-card-actions">
        <x-ui.button x-bind:class="{ 'is-idle': ! dirty }"
            variant="primary"
            size="sm"
            wire:click="save"
        >
            {{ __('client.business.actions.save') }}</x-ui.button>
    </div>
    </div>
</x-ui.card>
