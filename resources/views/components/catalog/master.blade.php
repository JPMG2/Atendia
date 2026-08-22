@props([
    'rows' => [],        // semilla del riel, CONGELADA (ver el #[Locked] del editor)
    'path' => 'form.data', // dónde vive el DTO en el server: SIEMPRE `$data` de BaseCatalogForm
    'search' => [],      // claves de la fila por las que filtra el buscador
    'rules' => [],       // espejo de getValidationRules() para form-guard.js
])

{{--
    Envoltorio del maestro: monta `catalogMaster()` y ofrece las dos vistas
    (tabla ⇄ formulario) por slot. Todo el estado del editor —view, mode, current,
    errors, filtered(), openCreate(), openEdit(), submit()— vive en la fábrica
    compartida de resources/js/catalog-master.js, no copiado en cada editor.

    El formulario NO tiene estado en Alpine: los campos van por wire:model contra
    el DTO del server. `current` es solo la fila abierta, para el título.

    La semilla del x-data es una propiedad #[Locked] del componente: si cambiara
    en un re-render, Livewire re-escribiría el atributo y Alpine RE-INICIALIZARÍA
    el editor perdiendo `view` y `mode`. La tabla se mantiene al día por el
    evento `catalog-rows-refreshed`, nunca por este atributo.
--}}
<div class="catalog-master"
    x-data="catalogMaster({
        items: {{ \Illuminate\Support\Js::from($rows) }},
        path: {{ \Illuminate\Support\Js::from($path) }},
        search: {{ \Illuminate\Support\Js::from($search) }},
        rules: {{ \Illuminate\Support\Js::from($rules) }}
    })"
    x-on:catalog-rows-refreshed="items = $event.detail.rows">

    <div class="catalog-view" x-show="view === 'list'">
        {{ $list }}
    </div>

    <div class="catalog-view" x-show="view === 'form'" x-cloak>
        {{ $form }}
    </div>
</div>
