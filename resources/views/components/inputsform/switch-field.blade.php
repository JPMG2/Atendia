@props([
    'label' => null,
    'hint' => null,        // standing description under the field
    'name' => null,
    'id' => null,
    'on' => null,          // word describing the on value ("Activa", "Sí")
    'off' => null,         // the same for the off one ("Inactiva", "No")
    'span' => 'short',     // width BY CONTENT: code | short | text | long | full
])

@php
    $id = $id ?? ($name ? 'if-'.$name : ($label ? 'if-'.\Illuminate\Support\Str::slug($label) : null));

    $on = $on ?? __('catalog.common.on');
    $off = $off ?? __('catalog.common.off');

    $descId = $id ? $id.'-desc' : null;

    // A field's width is declared by what the field IS, never in columns:
    // `.catalog-form` hands out the slack, so no row is left ragged on the right.
    // A map and not concatenation, so an invalid value falls back to the default.
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
