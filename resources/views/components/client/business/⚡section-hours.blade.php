<?php

use Livewire\Component;

/**
 * Opening-hours card of the "Mi negocio" mock-up: one row per day, several
 * shifts per row — the split day LatAm businesses actually keep. Monday can
 * be stamped onto the weekdays in one click, GBP-style.
 */
new class extends Component
{
    /** @var array<int, list<string>> mock schedule: day-of-week => shifts */
    public array $shifts = [
        1 => ['09:00 – 13:00', '16:00 – 20:00'],
        2 => ['09:00 – 13:00', '16:00 – 20:00'],
        3 => ['09:00 – 13:00', '16:00 – 20:00'],
        4 => ['09:00 – 13:00', '16:00 – 20:00'],
        5 => ['09:00 – 18:00'],
        6 => ['09:00 – 13:00'],
        0 => [],
    ];

    public function applyWeekdays(): void
    {
        foreach ([2, 3, 4, 5] as $day) {
            $this->shifts[$day] = $this->shifts[1];
        }
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
            <x-ui.button variant="ghost" size="sm" wire:click="applyWeekdays">{{ __('client.business.hours.apply_weekdays') }}</x-ui.button>
        </span>
    </div>
    <p class="bp-card-sub">{{ __('client.business.hours.sub') }}</p>

    <div class="bp-hours">
        @foreach ([1, 2, 3, 4, 5, 6, 0] as $day)
            <div wire:key="day-{{ $day }}" @class(['bp-hrow', 'is-closed' => $shifts[$day] === []])>
                <span class="bp-hday">{{ $days[$day] }}</span>
                <x-ui.switch name="day-{{ $day }}" size="sm" :checked="$shifts[$day] !== []" />
                @forelse ($shifts[$day] as $range)
                    <span class="bp-shift font-mono">{{ $range }}</span>
                @empty
                    <span class="bp-closed">{{ __('client.business.hours.closed') }}</span>
                @endforelse
                @if ($shifts[$day] !== [])
                    <button type="button" class="bp-hadd">+ {{ __('client.business.hours.add_shift') }}</button>
                @endif
            </div>
        @endforeach
    </div>

    <div class="bp-card-actions">
        <x-ui.button variant="primary" size="sm">{{ __('client.business.actions.save') }}</x-ui.button>
    </div>
</x-ui.card>
