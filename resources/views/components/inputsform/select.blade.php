@props([
    'label' => null,
    'hint' => null,        // standing description under the select
    'error' => null,       // Laravel error; with none passed it is read from the ErrorBag by `name`
    'alpineError' => null, // key in the Alpine `errors` bag (e.g. "currency_id"): border and message follow it
    'name' => null,
    'id' => null,
    'size' => 'm',         // s | m | l
    'options' => [],       // ['a' => 'Label'] | ['a','b'] | [['value'=>..,'label'=>..]]
    'placeholder' => null,
    'span' => 'text',      // width BY CONTENT: code | short | text | long | full
])

@php
    $id = $id ?? ($name ? 'if-'.$name : ($label ? 'if-'.\Illuminate\Support\Str::slug($label) : null));
    $error = $error ?? ($name && isset($errors) && $errors->has($name) ? $errors->first($name) : null);

    $sizeClass = ['s' => 'field-sm', 'l' => 'field-lg'][$size] ?? '';

    $isDisabled = $attributes->has('disabled') && $attributes->get('disabled') !== false;
    $isRequired = $attributes->has('required') && $attributes->get('required') !== false;

    // The prop takes only the key; the component builds the Alpine expression
    // against the `errors` bag, so no Blade ever writes that expression by hand.
    $alpineErrorExpr = $alpineError !== null ? 'errors.'.$alpineError : null;

    // Marks the field required (asterisk + aria-required) but WITHOUT the native
    // `required`, so the browser's own validation never overrides our validate().
    $selectAttributes = $isRequired ? $attributes->except('required') : $attributes;

    $selectClasses = trim('field-select '.$sizeClass
        .($error ? ' field-error' : '')
        .($isDisabled ? ' is-disabled' : ''));

    $descId = $id ? $id.'-desc' : null;
    $errId = $id ? $id.'-err' : null;
    $describedBy = trim(($error && $errId ? $errId.' ' : '').($hint && $descId ? $descId : '')) ?: null;

    // A field's width is declared by what the field IS, never in columns:
    // `.catalog-form` hands out the slack, so no row is left ragged on the right.
    // A map and not concatenation, so an invalid value falls back to the default.
    $spanClass = ['code' => 'f-code', 'short' => 'f-short', 'text' => 'f-text',
        'long' => 'f-long', 'full' => 'f-full'][$span] ?? 'f-text';
@endphp

<div class="field {{ $spanClass }}">
    @if ($label)
        <label for="{{ $id }}" class="field-label">{{ $label }}@if ($isRequired)<span class="field-required" aria-hidden="true">*</span>@endif</label>
    @endif

    <div class="field-select-wrap">
        <select
            @if ($id) id="{{ $id }}" @endif
            @if ($name) name="{{ $name }}" @endif
            @if ($error) aria-invalid="true" @endif
            @if ($alpineErrorExpr) x-bind:aria-invalid="!!({{ $alpineErrorExpr }}) || null" @endif
            @if ($isRequired) aria-required="true" @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            {{ $selectAttributes->merge(['class' => $selectClasses]) }}
        >
            @if ($placeholder)
                <option value="">{{ $placeholder }}</option>
            @endif
            @foreach ($options as $key => $opt)
                @php
                    [$val, $lbl] = is_array($opt)
                        ? [$opt['value'], $opt['label']]
                        : (is_string($key) ? [$key, $opt] : [$opt, $opt]);
                @endphp
                <option value="{{ $val }}">{{ $lbl }}</option>
            @endforeach
            {{ $slot }}
        </select>
        <span class="field-select-chevron" aria-hidden="true">▾</span>
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
