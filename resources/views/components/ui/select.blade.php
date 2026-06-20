@props([
    'label' => null,
    'hint' => null,
    'error' => null,
    'name' => null,
    'id' => null,
    'size' => 'md',         // sm | md | lg
    'options' => [],        // ['a' => 'Label'] | ['a','b'] | [['value'=>..,'label'=>..]]
    'placeholder' => null,
])

@php
    $id = $id ?? ($name ? 'sel-'.$name : ($label ? 'sel-'.\Illuminate\Support\Str::slug($label) : null));
    $error = $error ?? ($name && isset($errors) && $errors->has($name) ? $errors->first($name) : null);

    $sizeClass = ['sm' => 'field-sm', 'lg' => 'field-lg'][$size] ?? '';
    $selectClasses = trim('field-select '.$sizeClass.($error ? ' field-error' : ''));
@endphp

<div class="field">
    @if ($label)
        <label for="{{ $id }}" class="field-label">{{ $label }}</label>
    @endif

    <div class="field-select-wrap">
        <select
            id="{{ $id }}"
            @if ($name) name="{{ $name }}" @endif
            {{ $attributes->merge(['class' => $selectClasses]) }}
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
        <span class="field-select-chevron">▾</span>
    </div>

    @if ($error)
        <span class="field-error-text">{{ $error }}</span>
    @elseif ($hint)
        <span class="field-hint">{{ $hint }}</span>
    @endif
</div>
