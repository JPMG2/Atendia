@props([
    'searchPlaceholder' => null,
    'searchLabel' => null,
    'singular' => null,     // "moneda" — el contador concuerda en singular/plural
    'plural' => null,
    'create' => null,       // texto del botón de alta
])

{{--
    The master's top bar: search, count, create. Identical in every editor, so
    it lives here once. The count and the filter both come from `filtered()`.
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
