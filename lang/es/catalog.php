<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Catálogos (maestros del sistema)
|--------------------------------------------------------------------------
|
| Base NEUTRA (tuteo): cubre VE, CO, MX, CL y el resto. `es_AR` solo lleva
| overrides de voseo. Hoy este archivo no necesita ninguno: no hay un solo
| verbo en segunda persona ("Volver", "Cancelar", "Guardar cambios" son
| infinitivos), así que el texto es igual en todas las variantes.
|
| `common` es la chrome del editor, igual en todos los maestros. Lo que cambia
| con el género del maestro (moneda es femenina: "Nueva", "Activa") vive en la
| sección del maestro, no acá.
|
*/

return [

    'common' => [
        'back' => 'Volver',
        'cancel' => 'Cancelar',
        'save' => 'Guardar cambios',
        'delete' => 'Eliminar',
        'editing' => 'Editando',
    ],

    'currency' => [
        'search_placeholder' => 'Buscar por código o nombre',
        'search_label' => 'Buscar moneda',
        'singular' => 'moneda',
        'plural' => 'monedas',
        'create' => 'Crear moneda',
        'new' => 'Nueva',
        'new_title' => 'Nueva moneda',
        'edit_title' => 'Editar',
        'empty' => 'No hay monedas que coincidan con la búsqueda.',

        'columns' => [
            'code' => 'Código',
            'name' => 'Nombre',
            'symbol' => 'Símbolo',
            'decimals' => 'Decimales',
            'status' => 'Estado',
        ],

        'status' => [
            'active' => 'Activa',
            'inactive' => 'Inactiva',
        ],

        'fields' => [
            'code' => 'Código ISO',
            'code_hint' => '3 letras (ARS, USD)',
            'name' => 'Nombre',
            'name_placeholder' => 'Ej. Dólar Estadounidense',
            'symbol' => 'Símbolo',
            'symbol_hint' => 'Cómo se muestra: $, US$, €',
            'decimals' => 'Decimales',
        ],

        'active_title' => 'Moneda activa',
        'active_desc' => 'Disponible para elegir en precios y facturación.',
    ],

];
