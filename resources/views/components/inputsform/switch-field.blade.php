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
The status in the SAME box and height as an input, so it joins the form row
as one more control instead of spending a whole row on a boolean.

BOTH words always render and CSS shows the right one. The Alpine version
read the checkbox ONCE, so a morph changed `checked` while the text stayed
frozen. Tied to the DOM by CSS, the word follows the control.
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
