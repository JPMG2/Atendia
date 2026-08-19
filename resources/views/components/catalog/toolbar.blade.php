@props([
    'searchPlaceholder' => null,
    'searchLabel' => null,
    'singular' => null,     // "moneda" — el contador concuerda en singular/plural
    'plural' => null,
    'create' => null,       // texto del botón de alta
])

{{--
    Barra superior del maestro: buscar · contar · crear. Idéntica en los tres
    editores, así que vive acá una sola vez. El contador y el filtro salen de
    `filtered()`, que aporta el riel de Alpine (`catalogMaster`).
--}}
<div class="catalog-toolbar">
    <x-inputsform.input name="q" size="s" icon="search" :placeholder="$searchPlaceholder" x-model="q"
        :aria-label="$searchLabel" />

    <span class="catalog-count">
        <b x-text="filtered().length"></b>
        <span x-text="filtered().length === 1 ? {{ \Illuminate\Support\Js::from($singular) }} : {{ \Illuminate\Support\Js::from($plural) }}"></span>
    </span>

    <x-ui.button variant="primary" icon="plus" x-on:click="openCreate()">{{ $create }}</x-ui.button>
</div>
