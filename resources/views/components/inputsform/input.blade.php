@props([
    'label' => null,
    'hint' => null,        // standing description under the input
    'error' => null,       // Laravel error; with none passed it is read from the ErrorBag by `name`
    'alpineError' => null, // key in the Alpine `errors` bag (e.g. "code" → errors.code): border and message follow it
    'name' => null,
    'id' => null,
    'size' => 'm',         // s | m | l
    'icon' => null,        // icon on the left, inside the control
    'iconRight' => null,   // icon on the right
    'span' => 'text',      // width BY CONTENT: code | short | text | long | full
])

@php
    $id = $id ?? ($name ? 'if-'.$name : ($label ? 'if-'.\Illuminate\Support\Str::slug($label) : null));
    $error = $error ?? ($name && isset($errors) && $errors->has($name) ? $errors->first($name) : null);

    $sizeClass = ['s' => 'field-sm', 'l' => 'field-lg'][$size] ?? '';
    $iconSize = ['s' => 16, 'l' => 20][$size] ?? 18;

    $isReadonly = $attributes->has('readonly') && $attributes->get('readonly') !== false;
    $isDisabled = $attributes->has('disabled') && $attributes->get('disabled') !== false;
    $isRequired = $attributes->has('required') && $attributes->get('required') !== false;

    // Same peek and Caps Lock warning as <x-ui.input>: a masked field you
    // cannot check is a typo waiting to lock the owner out.
    $isPassword = $attributes->get('type') === 'password';

    // The prop takes only the key; the component builds the Alpine expression
    // against the `errors` bag, so no Blade ever writes that expression by hand.
    $alpineErrorExpr = $alpineError !== null ? 'errors.'.$alpineError : null;

    // Marks the field required (asterisk + aria-required) but WITHOUT the native
    // `required`, so the browser's own validation never overrides our validate().
    $inputAttributes = $isRequired ? $attributes->except('required') : $attributes;

    $controlClasses = trim('field-control '.$sizeClass
        .($error ? ' field-error' : '')
        .($isReadonly ? ' is-readonly' : '')
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

<div class="field {{ $spanClass }}" @if ($isPassword) x-data="{ showPw: false, capsOn: false }" @endif>
    @if ($label)
        <label for="{{ $id }}" class="field-label"
            >{{ $label }}
            @if ($isRequired)
                <span class="field-required" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    <div class="{{ $controlClasses }}">
        @if ($icon)
            <span class="field-icon"><x-icon :name="$icon" :size="$iconSize" /></span>
        @endif

        <input
            @if ($id) id="{{ $id }}" @endif
            @if ($name) name="{{ $name }}" @endif
            @if ($error) aria-invalid="true" @endif
            @if ($alpineErrorExpr) x-bind:aria-invalid="!!({{ $alpineErrorExpr }}) || null" @endif
            @if ($isRequired) aria-required="true" @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            @if ($isPassword)
                x-bind:type="showPw ? 'text' : 'password'"
                x-on:keydown="capsOn = $event.getModifierState?.('CapsLock') ?? false"
                x-on:keyup="capsOn = $event.getModifierState?.('CapsLock') ?? false"
                x-on:blur="capsOn = false"
            @endif
            {{ $inputAttributes->merge(['class' => 'field-input']) }}
        />

        @if ($isPassword)
            <button
                type="button"
                class="field-peek"
                x-on:click="showPw = ! showPw"
                x-bind:aria-label="showPw ? @js(__('forms.password.hide')) : @js(__('forms.password.show'))"
            >
                <span x-show="! showPw"><x-icon name="eye" :size="$iconSize" /></span>
                <span x-show="showPw" x-cloak><x-icon name="eye-off" :size="$iconSize" /></span>
            </button>
        @elseif ($iconRight)
            <span class="field-icon"><x-icon :name="$iconRight" :size="$iconSize" /></span>
        @endif
    </div>

    @if ($isPassword)
        <span class="field-caps" x-show="capsOn" x-cloak role="status">
            <x-icon name="triangle-alert" :size="14" />
            {{ __('forms.password.caps') }}
        </span>
    @endif

    {{-- Hint and error stack in .field-meta so they NEVER overlap. --}}
    @if ($hint || $error || $alpineErrorExpr)
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
        </div>
    @endif
</div>
