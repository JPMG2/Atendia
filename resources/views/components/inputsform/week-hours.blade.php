@props([
    'label' => null,
    'hint' => null,
    'name' => 'opening_schedule',   // key the validator reports errors under
    'model' => null,               // Livewire path of the week, e.g. form.data.opening_schedule
    'value' => [],                 // the week as the form holds it; any shape formState reads
    'span' => 'full',              // width BY CONTENT: a week of seven rows wants the whole row
])

@php
    $spanClass = ['code' => 'f-code', 'short' => 'f-short', 'text' => 'f-text',
        'long' => 'f-long', 'full' => 'f-full'][$span] ?? 'f-full';

    $week = \App\Classes\Main\OpeningHours::formState($value);
    $days = \App\Models\BusinessHour::dayNames();
@endphp

{{--
One shift per day, Monday first. Plain text for the hours and not a native
time input: that picker clips the minutes at this width. Each box reports its
own error, keyed on the stored shape: `name.day.0` opens, `name.day.1` closes.
--}}
<div class="field {{ $spanClass }}">
    @if ($label)
        <span class="field-label">{{ $label }}</span>
    @endif

    <div class="bp-hours" data-week-hours>
        @foreach (\App\Classes\Main\OpeningHours::DAY_ORDER as $day)
            <div wire:key="week-{{ $name }}-{{ $day }}" @class(['bp-hrow', 'is-closed' => ! $week[$day]['open']])>
                <span class="bp-hday">{{ $days[$day] }}</span>

                <x-ui.switch
                    size="sm"
                    :name="$name.'-'.$day"
                    :checked="$week[$day]['open']"
                    :aria-label="$days[$day]"
                    wire:model.live="{{ $model }}.{{ $day }}.open"
                />

                <div class="bp-hshifts">
                    @if ($week[$day]['open'])
                        <span class="bp-shift-edit">
                            <x-inputsform.input
                                type="text"
                                size="s"
                                maxlength="5"
                                inputmode="numeric"
                                class="font-mono"
                                :name="$name.'.'.$day.'.0'"
                                :placeholder="__('company.hours.time_placeholder')"
                                :aria-label="$days[$day].' · '.__('company.hours.opens')"
                                wire:model="{{ $model }}.{{ $day }}.opens_at"
                            />
                            <span class="bp-shift-sep" aria-hidden="true">–</span>
                            <x-inputsform.input
                                type="text"
                                size="s"
                                maxlength="5"
                                inputmode="numeric"
                                class="font-mono"
                                :name="$name.'.'.$day.'.1'"
                                :placeholder="__('company.hours.time_placeholder')"
                                :aria-label="$days[$day].' · '.__('company.hours.closes')"
                                wire:model="{{ $model }}.{{ $day }}.closes_at"
                            />
                        </span>
                    @else
                        <span class="bp-closed">{{ __('company.hours.closed') }}</span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <div class="field-meta">
        @if ($hint)
            <p class="field-hint">{{ $hint }}</p>
        @endif

        <p class="field-hint">{{ __('company.hours.overnight_hint') }}</p>
    </div>
</div>
