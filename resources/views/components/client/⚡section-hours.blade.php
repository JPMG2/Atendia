<?php

use App\Livewire\Forms\Client\HoursForm;
use App\Traits\HasNotifications;
use Livewire\Component;

/**
 * Opening-hours card of "Mi negocio". The week state, validation and save
 * live in {@see HoursForm}; the component only wires the screen to it.
 */
new class extends Component
{
    use HasNotifications;

    public HoursForm $form;

    public function mount(): void
    {
        $this->form->setup();
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
    @php
        $days = App\Models\BusinessHour::dayNames();
    @endphp

    <div class="bp-card-head">
        <h2>{{ __('client.business.hours.title') }}</h2>
        <span class="bp-chip">{{ __('client.business.recommended') }}</span>
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
                            {{-- Plain text and not type="time": the native picker clips
                            the minutes at this width; the server validates H:i. --}}
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

    <div class="bp-card-actions">
        <x-ui.button
            variant="primary"
            size="sm"
            wire:click="save"
        >
            {{ __('client.business.actions.save') }}</x-ui.button>
    </div>
</x-ui.card>
