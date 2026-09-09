@props([
    'label' => null,
    'hint' => null,        // standing description under the field
    'error' => null,       // Laravel error; with none passed it is read from the ErrorBag by `name`
    'alpineError' => null, // key in the Alpine `errors` bag: border and message follow it
    'name' => null,
    'id' => null,
    'rows' => 3,
    'span' => 'full',      // width BY CONTENT: code | short | text | long | full
    'counter' => false,    // opt-in live character counter; needs a maxlength
])

@php
    $id = $id ?? ($name ? 'if-'.$name : ($label ? 'if-'.\Illuminate\Support\Str::slug($label) : null));
    $error = $error ?? ($name && isset($errors) && $errors->has($name) ? $errors->first($name) : null);

    // The prop takes only the key; the component builds the Alpine expression
    // against the `errors` bag, so no Blade ever writes that expression by hand.
    $alpineErrorExpr = $alpineError !== null ? 'errors.'.$alpineError : null;

    $isDisabled = $attributes->has('disabled') && $attributes->get('disabled') !== false;

    // Marks the field required (asterisk + aria-required) but WITHOUT the native
    // `required`, so the browser's own validation never overrides our validate().
    $isRequired = $attributes->has('required') && $attributes->get('required') !== false;
    $fieldAttributes = $isRequired ? $attributes->except('required') : $attributes;

    $controlClasses = trim('field-control field-control-multiline'
        .($error ? ' field-error' : '')
        .($isDisabled ? ' is-disabled' : ''));

    $descId = $id ? $id.'-desc' : null;
    $errId = $id ? $id.'-err' : null;
    $describedBy = trim(($error && $errId ? $errId.' ' : '').($hint && $descId ? $descId : '')) ?: null;

    $spanClass = ['code' => 'f-code', 'short' => 'f-short', 'text' => 'f-text',
        'long' => 'f-long', 'full' => 'f-full'][$span] ?? 'f-full';

    // The counter lives off the same maxlength that stops the typing, so the
    // two can never disagree; it warms to the danger tone at 90%.
    $maxlength = (int) $attributes->get('maxlength', 0);
    $showCounter = $counter !== false && $maxlength > 0;
    $nearAt = (int) floor($maxlength * 0.9);
@endphp

<div
    class="field {{ $spanClass }}"
    @if ($showCounter) x-data="{ count: 0 }" x-init="count = $refs.ta.value.length" @endif
>
    @if ($label)
        <label for="{{ $id }}" class="field-label"
            >{{ $label }}
            @if ($isRequired)
                <span class="field-required" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    <div class="{{ $controlClasses }}">
        <textarea
            @if ($id) id="{{ $id }}" @endif
            @if ($name) name="{{ $name }}" @endif
            rows="{{ $rows }}"
            @if ($error) aria-invalid="true" @endif
            @if ($alpineErrorExpr) x-bind:aria-invalid="!!({{ $alpineErrorExpr }}) || null" @endif
            @if ($isRequired) aria-required="true" @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            @if ($showCounter) x-ref="ta" x-on:input="count = $el.value.length" @endif
            {{ $fieldAttributes->merge(['class' => 'field-input']) }}
        >{{ $slot }}</textarea>
    </div>

    {{-- Hint and error stack in .field-meta so they NEVER overlap. --}}
    @if ($hint || $error || $alpineErrorExpr || $showCounter)
        <div class="field-meta">
            @if ($hint)
                <span @if ($descId) id="{{ $descId }}" @endif class="field-hint">{{ $hint }}</span>
            @endif

            @if ($error)
                <span @if ($errId) id="{{ $errId }}" @endif class="field-error-text">{{ $error }}</span>
            @elseif ($alpineErrorExpr)
                <span
                    @if ($errId) id="{{ $errId }}" @endif
                    class="field-error-text"
                    x-show="!!({{ $alpineErrorExpr }})"
                    x-text="{{ $alpineErrorExpr }}"
                    x-cloak
                ></span>
            @endif

            @if ($showCounter)
                <span class="field-counter" x-bind:class="{ 'is-near': count >= {{ $nearAt }} }"
                    ><span x-text="count">0</span>/{{ $maxlength }}</span>
            @endif
        </div>
    @endif
</div>
