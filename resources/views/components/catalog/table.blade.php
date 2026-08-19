@props([
    'columns' => [],   // [['label' => '...', 'class' => 'catalog-col-name'], ...]
    'empty' => null,   // texto cuando la búsqueda no encuentra nada
])

{{--
    Tabla del maestro. El slot aporta SOLO las <td> de una fila; el componente
    pone el encabezado, el `x-for`, la fila vacía y la celda del chevron.

    La variable de la fila es `row` en los tres maestros (antes era `c` en uno y
    `n` en otro, por copiar y pegar).

    El `colspan` del vacío se calcula de `columns`: escrito a mano se
    desincronizaba al agregar una columna y el mensaje quedaba corrido.
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
