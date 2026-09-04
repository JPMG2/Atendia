@props([
    'label' => null,
    'hint' => null,
    'error' => null,       // Laravel error; with none passed it is read from the ErrorBag by `name`
    'alpineError' => null, // key in the Alpine `errors` bag: border and message follow it
    'name' => null,
    'id' => null,
    'span' => 'short',     // width BY CONTENT: code | short | text | long | full
    'placeholder' => null,
    'countries' => [],     // [['code' => '54', 'flag' => '🇦🇷'], ...] straight from the catalog
    'value' => null,       // the stored composite: "+54 3415124408"
    'defaultDial' => null, // preselected dial when the value carries none
])

@php
    $id = $id ?? ($name ? 'if-'.$name : ($label ? 'if-'.\Illuminate\Support\Str::slug($label) : null));
    $error = $error ?? ($name && isset($errors) && $errors->has($name) ? $errors->first($name) : null);

    $isRequired = $attributes->has('required') && $attributes->get('required') !== false;

    $alpineErrorExpr = $alpineError !== null ? 'errors.'.$alpineError : null;

    // `wire:model` and friends go on the hidden field, which is the real one;
    // the visible pair carries no `name`, so a native submit never posts it.
    $valueAttributes = $isRequired ? $attributes->except('required') : $attributes;

    $dials = collect($countries)->pluck('code')->map(fn ($code): string => (string) $code)->values()->all();

    $controlClasses = trim('field-control phone-control'.($error ? ' field-error' : ''));

    $descId = $id ? $id.'-desc' : null;
    $errId = $id ? $id.'-err' : null;
    $describedBy = trim(($error && $errId ? $errId.' ' : '').($hint && $descId ? $descId : '')) ?: null;

    $spanClass = ['code' => 'f-code', 'short' => 'f-short', 'text' => 'f-text',
        'long' => 'f-long', 'full' => 'f-full'][$span] ?? 'f-short';
@endphp

<div class="field {{ $spanClass }}">
    @if ($label)
        <label for="{{ $id }}" class="field-label">{{ $label }}@if ($isRequired)<span class="field-required" aria-hidden="true">*</span>@endif</label>
    @endif

    <div class="{{ $controlClasses }}"
        x-data="inputsformPhone({ value: @js((string) $value), defaultDial: @js((string) $defaultDial), dials: @js($dials) })">
        <select class="phone-dial" x-model="dial" tabindex="-1" aria-label="{{ __('forms.phone.country') }}">
            @foreach ($countries as $country)
                <option value="{{ $country['code'] }}">{{ $country['flag'] }} +{{ $country['code'] }}</option>
            @endforeach
        </select>

        <input
            type="tel"
            inputmode="tel"
            autocomplete="tel-national"
            x-model="number"
            @if ($id) id="{{ $id }}" @endif
            @if ($placeholder) placeholder="{{ $placeholder }}" @endif
            @if ($error) aria-invalid="true" @endif
            @if ($alpineErrorExpr) x-bind:aria-invalid="!!({{ $alpineErrorExpr }}) || null" @endif
            @if ($isRequired) aria-required="true" @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            class="field-input phone-number"
        />

        <input type="hidden" x-ref="real"
            @if ($name) name="{{ $name }}" @endif
            value="{{ $value }}"
            {{ $valueAttributes->whereStartsWith('wire:') }} />
    </div>

    {{-- Hint and error stack in .field-meta so they NEVER overlap. --}}
    @if ($hint || $error || $alpineErrorExpr)
        <div class="field-meta">
            @if ($hint)
                <span @if ($descId) id="{{ $descId }}" @endif class="field-hint">{{ $hint }}</span>
            @endif

            @if ($error)
                <span @if ($errId) id="{{ $errId }}" @endif class="field-error-text">{{ $error }}</span>
            @elseif ($alpineErrorExpr)
                <span @if ($errId) id="{{ $errId }}" @endif class="field-error-text" x-show="!!({{ $alpineErrorExpr }})" x-text="{{ $alpineErrorExpr }}" x-cloak></span>
            @endif
        </div>
    @endif
</div>
