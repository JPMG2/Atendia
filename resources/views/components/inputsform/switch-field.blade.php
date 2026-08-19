@props([
    'label' => null,
    'hint' => null,        // descripción persistente bajo el campo
    'name' => null,
    'id' => null,
    'on' => null,          // palabra que describe el valor encendido ("Activa", "Sí")
    'off' => null,         // ídem apagado ("Inactiva", "No")
    'span' => 'short',     // ancho POR CONTENIDO: code | short | text | long | full
])

@php
    $id = $id ?? ($name ? 'if-'.$name : ($label ? 'if-'.\Illuminate\Support\Str::slug($label) : null));

    $on = $on ?? __('catalog.common.on');
    $off = $off ?? __('catalog.common.off');

    $descId = $id ? $id.'-desc' : null;

    // El ancho de un campo se declara por lo que el campo ES, nunca en columnas:
    // `.catalog-form` reparte el sobrante y así ninguna fila queda ragged a la
    // derecha. Mapa (no concatenación) para que un valor inválido caiga al default.
    $spanClass = ['code' => 'f-code', 'short' => 'f-short', 'text' => 'f-text',
        'long' => 'f-long', 'full' => 'f-full'][$span] ?? 'f-short';
@endphp

{{--
    El estado con la MISMA caja y altura que un input, para que entre en la fila
    del formulario como un control más. Antes vivía en un bloque a lo ancho al
    pie, que gastaba una fila entera en un booleano.

    Las DOS palabras se renderizan siempre y el CSS muestra la que corresponde
    con `:has(input:checked)`. No es una maña: la versión con Alpine
    (`x-init="on = $el.checked"`) leía el checkbox UNA sola vez, y cuando
    Livewire hacía morph al abrir un registro el `checked` cambiaba pero el
    texto quedaba congelado — se veía "No" con la perilla encendida. Atado al
    DOM por CSS, la palabra sigue al control siempre: al tocarlo (sin esperar el
    round trip) y después de cualquier re-render.
--}}
<div class="field {{ $spanClass }}">
    @if ($label)
        <label for="{{ $id }}" class="field-label">{{ $label }}</label>
    @endif

    <div class="field-control field-switch">
        <span class="field-switch-state" aria-hidden="true">
            <span class="switch-word-on">{{ $on }}</span>
            <span class="switch-word-off">{{ $off }}</span>
        </span>

        <x-ui.switch
            :id="$id"
            :name="$name"
            :aria-describedby="$hint ? $descId : null"
            {{ $attributes }}
        />
    </div>

    @if ($hint)
        <div class="field-meta">
            <p id="{{ $descId }}" class="field-hint">{{ $hint }}</p>
        </div>
    @endif
</div>
