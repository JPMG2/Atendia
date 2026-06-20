@props([
    'label' => null,
    'hint' => null,
    'error' => null,        // si no se pasa, se toma del ErrorBag por `name`
    'name' => null,
    'id' => null,
    'size' => 'md',         // sm | md | lg
    'icon' => null,         // icono a la izquierda
    'iconRight' => null,    // icono a la derecha
])

@php
    $id = $id ?? ($name ? 'in-'.$name : ($label ? 'in-'.\Illuminate\Support\Str::slug($label) : null));
    $error = $error ?? ($name && isset($errors) && $errors->has($name) ? $errors->first($name) : null);

    $sizeClass = ['sm' => 'field-sm', 'lg' => 'field-lg'][$size] ?? '';
    $controlClasses = trim('field-control '.$sizeClass.($error ? ' field-error' : ''));
@endphp

<div class="field">
    @if ($label)
        <label for="{{ $id }}" class="field-label">{{ $label }}</label>
    @endif

    <div class="{{ $controlClasses }}">
        @if ($icon)<span class="field-icon"><x-icon :name="$icon" :size="18" /></span>@endif
        <input
            id="{{ $id }}"
            @if ($name) name="{{ $name }}" @endif
            {{ $attributes->merge(['class' => 'field-input']) }}
        />
        @if ($iconRight)<span class="field-icon"><x-icon :name="$iconRight" :size="18" /></span>@endif
    </div>

    @if ($error)
        <span class="field-error-text">{{ $error }}</span>
    @elseif ($hint)
        <span class="field-hint">{{ $hint }}</span>
    @endif
</div>
