@props([
    'label' => null,
    'hint' => null,
    'error' => null,        // with none passed it is read from the ErrorBag by `name`
    'alpineError' => null,  // key in the Alpine `errors` bag: border and message follow it
    'name' => null,
    'id' => null,
    'size' => 'md',         // sm | md | lg
    'icon' => null,         // icono a la izquierda
    'iconRight' => null,    // icon on the right
])

@php
    // Every password field ships the peek toggle: seeing what you typed is
    // the difference between a typo caught and a login locked out.
    $isPassword = $attributes->get('type') === 'password';

    $id = $id ?? ($name ? 'in-'.$name : ($label ? 'in-'.\Illuminate\Support\Str::slug($label) : null));
    $error = $error ?? ($name && isset($errors) && $errors->has($name) ? $errors->first($name) : null);

    // The prop takes only the key; the component builds the Alpine expression
    // against the `errors` bag, same wiring as <x-inputsform.input>.
    $alpineErrorExpr = $alpineError !== null ? 'errors.'.$alpineError : null;

    $sizeClass = ['sm' => 'field-sm', 'lg' => 'field-lg'][$size] ?? '';
    $controlClasses = trim('field-control '.$sizeClass.($error ? ' field-error' : ''));
@endphp

<div class="field" @if ($isPassword) x-data="{ showPw: false, capsOn: false }" @endif>
    @if ($label)
        <label for="{{ $id }}" class="field-label">{{ $label }}</label>
    @endif

    <div
        class="{{ $controlClasses }}"
        @if ($alpineErrorExpr) x-bind:class="{ 'field-error': !!({{ $alpineErrorExpr }}) }" @endif
    >
        @if ($icon)
            <span class="field-icon"><x-icon :name="$icon" :size="18" /></span>
        @endif
        <input
            id="{{ $id }}"
            @if ($name) name="{{ $name }}" @endif
            @if ($isPassword)
                x-bind:type="showPw ? 'text' : 'password'"
                x-on:keydown="capsOn = $event.getModifierState?.('CapsLock') ?? false"
                x-on:keyup="capsOn = $event.getModifierState?.('CapsLock') ?? false"
                x-on:blur="capsOn = false"
            @endif
            {{ $attributes->merge(['class' => 'field-input']) }}
        />
        @if ($isPassword)
            <button
                type="button"
                class="field-peek"
                x-on:click="showPw = ! showPw"
                x-bind:aria-label="showPw ? @js(__('forms.password.hide')) : @js(__('forms.password.show'))"
            >
                <span x-show="! showPw"><x-icon name="eye" :size="18" /></span>
                <span x-show="showPw" x-cloak><x-icon name="eye-off" :size="18" /></span>
            </button>
        @elseif ($iconRight)
            <span class="field-icon"><x-icon :name="$iconRight" :size="18" /></span>
        @endif
    </div>

    @if ($isPassword)
        {{-- Caps Lock types SHOUTING into a masked field: warn while it
        happens instead of letting the attempt fail in silence. --}}
        <span class="field-caps" x-show="capsOn" x-cloak role="status">
            <x-icon name="triangle-alert" :size="14" />
            {{ __('forms.password.caps') }}
        </span>
    @endif

    @if ($error)
        <span class="field-error-text">{{ $error }}</span>
    @else
        @if ($alpineErrorExpr)
            <span
                class="field-error-text"
                x-show="!!({{ $alpineErrorExpr }})"
                x-text="{{ $alpineErrorExpr }}"
                x-cloak
            ></span>
        @endif
        @if ($hint)
            <span
                class="field-hint"
                @if ($alpineErrorExpr) x-show="! ({{ $alpineErrorExpr }})" @endif
            >{{ $hint }}</span>
        @endif
    @endif
</div>
