@props([
    'label' => null,
    'hint' => null,        // descripción persistente bajo el select
    'error' => null,       // error de Laravel; si no se pasa, se toma del ErrorBag por `name`
    'alpineError' => null, // clave del bag Alpine `errors` (ej. "currency_id"): borde y mensaje siguen a ese estado
    'name' => null,
    'id' => null,
    'size' => 'm',         // s | m | l
    'options' => [],       // ['a' => 'Label'] | ['a','b'] | [['value'=>..,'label'=>..]]
    'placeholder' => null,
    'span' => 'text',      // ancho POR CONTENIDO: code | short | text | long | full
])

@php
    $id = $id ?? ($name ? 'if-'.$name : ($label ? 'if-'.\Illuminate\Support\Str::slug($label) : null));
    $error = $error ?? ($name && isset($errors) && $errors->has($name) ? $errors->first($name) : null);

    $sizeClass = ['s' => 'field-sm', 'l' => 'field-lg'][$size] ?? '';

    $isDisabled = $attributes->has('disabled') && $attributes->get('disabled') !== false;
    $isRequired = $attributes->has('required') && $attributes->get('required') !== false;

    // La prop recibe solo la clave; el componente arma la expresión Alpine contra
    // el bag `errors` (convención de validate()). Así el Blade nunca escribe la expresión.
    $alpineErrorExpr = $alpineError !== null ? 'errors.'.$alpineError : null;

    // Marca el campo como requerido (asterisco + aria-required) pero SIN el `required`
    // nativo, para que la validación del navegador no pise a nuestra validate().
    $selectAttributes = $isRequired ? $attributes->except('required') : $attributes;

    $selectClasses = trim('field-select '.$sizeClass
        .($error ? ' field-error' : '')
        .($isDisabled ? ' is-disabled' : ''));

    $descId = $id ? $id.'-desc' : null;
    $errId = $id ? $id.'-err' : null;
    $describedBy = trim(($error && $errId ? $errId.' ' : '').($hint && $descId ? $descId : '')) ?: null;

    // El ancho de un campo se declara por lo que el campo ES, nunca en columnas:
    // `.catalog-form` reparte el sobrante y así ninguna fila queda ragged a la
    // derecha. Mapa (no concatenación) para que un valor inválido caiga al default.
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
