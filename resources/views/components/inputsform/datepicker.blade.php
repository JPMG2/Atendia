@props([
    'label' => null,
    'hint' => null,
    'error' => null, // Laravel error; with none passed it is read from the ErrorBag by `name`
    'name' => null,
    'id' => null,
    'size' => 'm', // s | m | l
    'mode' => 'single', // single | range
    'value' => null, // ISO preselection: "Y-m-d" or "Y-m-d..Y-m-d"
    'placeholder' => null,
    'span' => 'text', // width BY CONTENT: code | short | text | long | full
])

@php
    $id = $id ?? ($name ? 'if-'.$name : ($label ? 'if-'.\Illuminate\Support\Str::slug($label) : null));
    $error = $error ?? ($name && isset($errors) && $errors->has($name) ? $errors->first($name) : null);

    $sizeClass = ['s' => 'field-sm', 'l' => 'field-lg'][$size] ?? '';
    $iconSize = ['s' => 16, 'l' => 20][$size] ?? 18;

    $isDisabled = $attributes->has('disabled') && $attributes->get('disabled') !== false;

    // Anything invalid falls back to a single date: a picker must never break
    // the form it sits in over a typo in a prop.
    $mode = in_array($mode, ['single', 'range'], true) ? $mode : 'single';

    $controlClasses = trim('field-control '.$sizeClass
        .($error ? ' field-error' : '')
        .($isDisabled ? ' is-disabled' : ''));

    $descId = $id ? $id.'-desc' : null;
    $errId = $id ? $id.'-err' : null;
    $describedBy = trim(($error && $errId ? $errId.' ' : '').($hint && $descId ? $descId : '')) ?: null;

    $spanClass = ['code' => 'f-code', 'short' => 'f-short', 'text' => 'f-text',
        'long' => 'f-long', 'full' => 'f-full'][$span] ?? 'f-text';
@endphp

<div
    class="field {{ $spanClass }}"
    x-data="inputsformDatepicker({ mode: {{ \Illuminate\Support\Js::from($mode) }}, initial: {{ \Illuminate\Support\Js::from((string) $value) }} })"
>
    @if ($label)
        <label for="{{ $id }}" class="field-label">{{ $label }}</label>
    @endif

    <div class="{{ $controlClasses }}">
        <span class="field-icon"><x-icon name="calendar" :size="$iconSize" /></span>

        {{-- Flatpickr owns this input (clicks pick, no typing). The wire:ignore
        wrapper keeps every Livewire morph off the date it painted. --}}
        <span wire:ignore class="field-input-wrap">
            <input
                type="text"
                autocomplete="off"
                x-ref="display"
                @if ($id) id="{{ $id }}" @endif
                @if ($placeholder) placeholder="{{ $placeholder }}" @endif
                @if ($isDisabled) disabled @endif
                @if ($error) aria-invalid="true" @endif
                @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
                class="field-input"
            />
        </span>

        {{-- Only when there is something to clear: a fixed cross over an
        empty field is noise. --}}
        <button
            type="button"
            class="combo-clear"
            tabindex="-1"
            x-show="hasValue"
            x-cloak
            aria-label="{{ __('forms.datepicker.clear') }}"
            @if ($isDisabled) disabled @endif
            x-on:click="clear()"
        >
            <x-icon name="x" :size="$iconSize - 2" />
        </button>

        {{-- The real value: it carries the wire:model and travels to the server. --}}
        <input
            type="hidden"
            x-ref="value"
            @if ($name) name="{{ $name }}" @endif
            value="{{ $value }}"
            {{ $attributes }}
        />
    </div>

    @if ($hint || $error)
        <div class="field-meta">
            @if ($hint)
                <span @if ($descId) id="{{ $descId }}" @endif class="field-hint">{{ $hint }}</span>
            @endif

            @if ($error)
                <span @if ($errId) id="{{ $errId }}" @endif class="field-error-text">{{ $error }}</span>
            @endif
        </div>
    @endif
</div>
