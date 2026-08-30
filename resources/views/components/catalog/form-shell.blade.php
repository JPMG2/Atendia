@props([
    'new' => null,        // badge del alta ("Nueva" / "Nuevo") — concuerda con el género
    'newTitle' => null,   // "Nueva red social"
    'editTitle' => null,  // "Editar" — al lado va el identificador de la fila
    'create' => null,     // texto del botón de alta
    'titleKey' => 'name', // campo de la fila en curso que se muestra junto a "Editar"
])

{{--
    The form's chrome: the top bar and the action footer. It was identical in
    every editor bar the copy, so a master now contributes ONLY its fields.
--}}
<div class="catalog-formbar">
    <button type="button" class="catalog-back" x-on:click="backToList()">
        <x-icon name="chevron-left" :size="15" /> {{ __('catalog.common.back') }}
    </button>

    <span class="catalog-form-badge"
        x-text="mode === 'edit' ? {{ \Illuminate\Support\Js::from(__('catalog.common.editing')) }} : {{ \Illuminate\Support\Js::from($new) }}"></span>

    <span class="catalog-form-title">
        <template x-if="mode === 'edit'">
            <span>{{ $editTitle }} <span class="mono" x-text="current.{{ $titleKey }}"></span></span>
        </template>
        <template x-if="mode === 'create'"><span>{{ $newTitle }}</span></template>
    </span>
</div>

<form class="catalog-form" x-on:submit.prevent="submit">
    {{ $slot }}
</form>

<div class="catalog-form-foot">
    <template x-if="mode === 'edit'">
        <x-ui.button variant="ghost" icon="trash-2" class="catalog-btn-danger"
            x-on:click="remove()">{{ __('catalog.common.delete') }}</x-ui.button>
    </template>
    <span class="catalog-foot-grow"></span>
    <x-ui.button variant="ghost" x-on:click="backToList()">{{ __('catalog.common.cancel') }}</x-ui.button>
    <x-ui.button variant="primary" icon="check" x-on:click="submit()">
        <span x-text="mode === 'edit' ? {{ \Illuminate\Support\Js::from(__('catalog.common.save')) }} : {{ \Illuminate\Support\Js::from($create) }}"></span>
    </x-ui.button>
</div>
