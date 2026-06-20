@props([
    'label' => null,
    'hint' => null,
    'error' => null,
    'name' => null,
    'id' => null,
    'rows' => 4,
])

@php
    $id = $id ?? ($name ? 'ta-'.$name : ($label ? 'ta-'.\Illuminate\Support\Str::slug($label) : null));
    $error = $error ?? ($name && isset($errors) && $errors->has($name) ? $errors->first($name) : null);
@endphp

<div class="field">
    @if ($label)
        <label for="{{ $id }}" class="field-label">{{ $label }}</label>
    @endif

    <textarea
        id="{{ $id }}"
        rows="{{ $rows }}"
        @if ($name) name="{{ $name }}" @endif
        {{ $attributes->merge(['class' => 'field-textarea'.($error ? ' field-error' : '')]) }}
    >{{ $slot }}</textarea>

    @if ($error)
        <span class="field-error-text">{{ $error }}</span>
    @elseif ($hint)
        <span class="field-hint">{{ $hint }}</span>
    @endif
</div>
