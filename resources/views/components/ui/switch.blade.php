@props([
    'name' => null,
    'label' => null,
    'value' => '1',
    'checked' => false,
    'size' => 'md',   // sm | md
    'id' => null,
])

@php
    $trackClass = 'switch-track'.($size === 'sm' ? ' switch-sm' : '');
@endphp

<label class="switch">
    <input
        type="checkbox"
        role="switch"
        @if ($id) id="{{ $id }}" @endif
        @if ($name) name="{{ $name }}" @endif
        value="{{ $value }}"
        @checked($checked)
        {{ $attributes }}
    />
    <span class="{{ $trackClass }}"></span>
    @if ($label)
        <span class="switch-label">{{ $label }}</span>
    @endif
</label>
