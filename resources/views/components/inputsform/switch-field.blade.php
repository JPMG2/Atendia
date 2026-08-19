@props([
    'label' => null,
    'hint' => null,        // descripción persistente bajo el campo
    'name' => null,
    'id' => null,
    'on' => null,          // palabra que describe el valor encendido ("Activa")
    'off' => null,         // ídem apagado ("Inactiva")
    'span' => 'text',      // ancho POR CONTENIDO: code | short | text | long | full
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
        'long' => 'f-long', 'full' => 'f-full'][$span] ?? 'f-text';
@endphp

{{--
    El estado con la MISMA caja y altura que un input, para que entre en la fila
    del formulario como un control más. Antes vivía en un bloque a lo ancho al
    pie (`catalog-switch-row`), que gastaba una fila entera en un booleano.

    El texto lo pinta Alpine leyendo el checkbox real, no una propiedad de
    Livewire: así cambia en el acto al tocar la perilla, sin esperar el round
    trip. `on` arranca en false y `x-init` lo sincroniza con el DOM ya
    renderizado por el servidor — la semilla del x-data es un literal constante,
    nunca un valor que Livewire pueda re-renderizar (si cambiara, Alpine
    re-inicializaría el componente y se perdería el estado del editor).
--}}
<div class="field {{ $spanClass }}" x-data="{ on: false }">
    @if ($label)
        <label for="{{ $id }}" class="field-label">{{ $label }}</label>
    @endif

    <div class="field-control field-switch">
        <span class="field-switch-state" x-text="on ? {{ \Illuminate\Support\Js::from($on) }} : {{ \Illuminate\Support\Js::from($off) }}">{{ $off }}</span>

        {{--
            Dentro del tag de un componente Blade SOLO se expande el literal
            `{{ $attributes }}`: cualquier otra variable ({{ $unaBag }}) se
            parsea como el NOMBRE de un atributo y el bag entero se pierde
            —incluido el wire:model, con lo que el switch nunca refleja el
            valor guardado—. Por eso el describedby se pasa como atributo
            bindeado: un valor null lo omite del render.
        --}}
        <x-ui.switch
            :id="$id"
            :name="$name"
            :aria-describedby="$hint ? $descId : null"
            x-init="on = $el.checked"
            x-on:change="on = $el.checked"
            {{ $attributes }}
        />
    </div>

    @if ($hint)
        <div class="field-meta">
            <p id="{{ $descId }}" class="field-hint">{{ $hint }}</p>
        </div>
    @endif
</div>
