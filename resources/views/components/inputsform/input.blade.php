@props([
    'label' => null,
    'hint' => null,        // descripción persistente bajo el input
    'error' => null,       // error de Laravel; si no se pasa, se toma del ErrorBag por `name`
    'alpineError' => null, // clave del bag Alpine `errors` (ej. "code" → errors.code): borde y mensaje siguen a ese estado
    'name' => null,
    'id' => null,
    'size' => 'm',         // s | m | l
    'icon' => null,        // icono a la izquierda, dentro del control
    'iconRight' => null,   // icono a la derecha
])

@php
    $id = $id ?? ($name ? 'if-'.$name : ($label ? 'if-'.\Illuminate\Support\Str::slug($label) : null));
    $error = $error ?? ($name && isset($errors) && $errors->has($name) ? $errors->first($name) : null);

    $sizeClass = ['s' => 'field-sm', 'l' => 'field-lg'][$size] ?? '';
    $iconSize = ['s' => 16, 'l' => 20][$size] ?? 18;

    $isReadonly = $attributes->has('readonly') && $attributes->get('readonly') !== false;
    $isDisabled = $attributes->has('disabled') && $attributes->get('disabled') !== false;
    $isRequired = $attributes->has('required') && $attributes->get('required') !== false;

    // La prop recibe solo la clave; el componente arma la expresión Alpine contra
    // el bag `errors` (convención de validate()). Así el Blade nunca escribe la expresión.
    $alpineErrorExpr = $alpineError !== null ? 'errors.'.$alpineError : null;

    // Marca el campo como requerido (asterisco + aria-required) pero SIN el `required`
    // nativo, para que la validación del navegador no pise a nuestra validate().
    $inputAttributes = $isRequired ? $attributes->except('required') : $attributes;

    $controlClasses = trim('field-control '.$sizeClass
        .($error ? ' field-error' : '')
        .($isReadonly ? ' is-readonly' : '')
        .($isDisabled ? ' is-disabled' : ''));

    $descId = $id ? $id.'-desc' : null;
    $errId = $id ? $id.'-err' : null;
    $describedBy = trim(($error && $errId ? $errId.' ' : '').($hint && $descId ? $descId : '')) ?: null;
@endphp

<div class="field">
    @if ($label)
        <label for="{{ $id }}" class="field-label">{{ $label }}@if ($isRequired)<span class="field-required" aria-hidden="true">*</span>@endif</label>
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
            {{ $inputAttributes->merge(['class' => 'field-input']) }}
        />

        @if ($iconRight)
            <span class="field-icon"><x-icon :name="$iconRight" :size="$iconSize" /></span>
        @endif
    </div>

    {{-- La descripción y el error coexisten apilados (.field-meta) para que NUNCA se solapen --}}
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
