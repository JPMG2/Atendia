@props([
    'searchPlaceholder' => null,
    'searchLabel' => null,
    'singular' => null,     // "currency"; the counter agrees in singular and plural
    'plural' => null,
    'create' => null,       // label of the create button
    'chipOptions' => [],    // the chip column's values in the server's order: the selector's list when chips would not fit
])

{{--
    The master's top bar: search, count, create. Identical in every editor, so
    it lives here once. The count and the filter both come from `filtered()`.
--}}
<div class="catalog-toolbar">
    <x-inputsform.input
        name="q"
        size="s"
        icon="search"
        :placeholder="$searchPlaceholder"
        x-model="q"
        :aria-label="$searchLabel"
    />

    <span class="catalog-count">
        <b x-text="filtered().length"></b>
        <span x-text="filtered().length === 1 ? {{ \Illuminate\Support\Js::from($singular) }} : {{ \Illuminate\Support\Js::from($plural) }}"></span>
    </span>

    {{-- What only this master offers (copy, a year view): buttons the editor brings with their own wiring. --}}
    {{ $slot }}

    {{-- An edit-only master passes no label: the capability does not exist, so the button is not drawn. --}}
    @if ($create)
        <x-ui.button variant="primary" icon="plus" x-on:click="openCreate()">{{ $create }}</x-ui.button>
    @endif
</div>

{{-- The one-tap filter of a master whose model names a chip column (a country): the phone draws the same row.
     Past `chipsMax` values the chips would fill the screen, so one country selector stands in, on both sides. --}}
<template x-if="chipKey && chips().length > chipsMax">
    <div class="catalog-filter">
        {{-- Its choice writes the browser's filter; its countries follow `chips()` after every save, since a
             Livewire morph never redraws what a <template> already drew; a chip reset elsewhere clears it too. --}}
        <x-inputsform.combobox
            span="text"
            size="s"
            name="catalog_chip"
            :label="__('catalog.hub.chips_label')"
            :placeholder="__('catalog.hub.chips_placeholder')"
            :options="$chipOptions"
            x-on:change="chip = $event.target.value"
            x-effect="
                options = chips().map((value) => ({ value, label: value }));
                chip === '' && selected && ((selected = null), (query = ''));
            "
        />
    </div>
</template>

<template x-if="chipKey && chips().length > 1 && chips().length <= chipsMax">
    <div class="catalog-filter" role="group" aria-label="{{ __('catalog.hub.chips_label') }}">
        <x-ui.button
            variant="secondary"
            size="sm"
            x-on:click="chip = ''"
            x-bind:class="{ 'btn-primary': chip === '', 'btn-secondary': chip !== '' }"
            x-bind:aria-pressed="chip === '' ? 'true' : 'false'"
        >
            {{ __('catalog.hub.chips_all') }}</x-ui.button>

        <template x-for="value in chips()" :key="value">
            <x-ui.button
                variant="secondary"
                size="sm"
                x-on:click="chip = value"
                x-bind:class="{ 'btn-primary': chip === value, 'btn-secondary': chip !== value }"
                x-bind:aria-pressed="chip === value ? 'true' : 'false'"
                x-text="value"
            ></x-ui.button>
        </template>
    </div>
</template>
