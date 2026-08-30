@props([
    'columns' => [],   // [['label' => '...', 'class' => 'catalog-col-name'], ...]
    'empty' => null,   // texto cuando la búsqueda no encuentra nada
])

{{--
    The master's table: the slot brings ONLY a row's cells, and the component
    puts in the header, the loop, the empty row and the chevron. That empty
    row's `colspan` is computed from the columns, since written by hand it
    drifted out of step whenever one was added.
--}}
<div class="catalog-table-wrap">
    <table class="catalog-table">
        <thead>
            <tr>
                @foreach ($columns as $column)
                    <th @class([$column['class'] ?? ''])>{{ $column['label'] }}</th>
                @endforeach
                <th class="catalog-gocell" aria-hidden="true"></th>
            </tr>
        </thead>
        <tbody>
            <template x-for="row in filtered()" :key="row.id">
                <tr x-on:click="openEdit(row)">
                    {{ $slot }}
                    <td class="catalog-gocell">
                        <span class="catalog-row-go"><x-icon name="chevron-right" :size="16" /></span>
                    </td>
                </tr>
            </template>

            <tr x-show="filtered().length === 0">
                <td colspan="{{ count($columns) + 1 }}" class="catalog-table-empty">{{ $empty }}</td>
            </tr>
        </tbody>
    </table>
</div>
