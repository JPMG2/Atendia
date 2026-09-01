@props([
    'rows' => [],        // rail seed, FROZEN (see the editor's #[Locked])
    'path' => 'form.data', // where the DTO lives on the server: ALWAYS BaseCatalogForm's `$data`
    'search' => [],      // row keys the search box filters on
    'rules' => [],       // mirror of getValidationRules() for form-guard.js
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
