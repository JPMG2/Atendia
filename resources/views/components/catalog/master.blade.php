@props([
    'rows' => [],        // semilla del riel, CONGELADA (ver el #[Locked] del editor)
    'path' => 'form.data', // dónde vive el DTO en el server: SIEMPRE `$data` de BaseCatalogForm
    'search' => [],      // claves de la fila por las que filtra el buscador
    'rules' => [],       // espejo de getValidationRules() para form-guard.js
])

{{--
    The master's wrapper: it mounts `catalogMaster()` and offers both views by
    slot. The editor's state lives in the shared factory, and the form holds NO
    Alpine state. The x-data seed is #[Locked]: were it to change on a
    re-render, Alpine would RE-INITIALISE the editor and lose the open view.
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
