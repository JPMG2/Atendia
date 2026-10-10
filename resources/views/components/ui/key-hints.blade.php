@props(['title' => null])

{{--
    The key line of a table screen, written once: `j`/`k` walk the rows on every
    sortable table, and `?` reads THIS line back as a list (table-enhance.js),
    so what a screen says and what the dialog lists cannot drift apart. A screen
    with keys of its own passes them in the slot, kbd followed by its words.
--}}
<p class="key-hints" data-help-title="{{ $title ?? __('admin.keys.title') }}">
    {{ $slot }}
    <kbd class="cmdk-kbd">j</kbd> <kbd class="cmdk-kbd">k</kbd> {{ __('admin.keys.rows') }}
    <kbd class="cmdk-kbd">?</kbd> {{ __('admin.keys.help') }}
</p>
