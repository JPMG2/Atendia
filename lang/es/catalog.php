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

    'country' => [
        'search_placeholder' => 'Buscar por código o nombre',
        'search_label' => 'Buscar país',
        'singular' => 'país',
        'plural' => 'países',
        'create' => 'Crear país',
        'new' => 'Nuevo',
        'new_title' => 'Nuevo país',
        'edit_title' => 'Editar',
        'empty' => 'No hay países que coincidan con la búsqueda.',

        'columns' => [
            'code' => 'Código',
            'name' => 'Nombre',
            'phone_code' => 'Cód. telefónico',
            'currency' => 'Moneda',
            'status' => 'Estado',
        ],

        'status' => [
            'active' => 'Activo',
            'inactive' => 'Inactivo',
        ],

        'fields' => [
            'code' => 'Código ISO',
            'code_hint' => '3 letras (ARG, USA)',
            'name' => 'Nombre',
            'name_placeholder' => 'Ej. República Dominicana',
            'phone_code' => 'Código telefónico',
            'phone_code_hint' => 'Sin el +: 54, 1809',
            'currency' => 'Moneda',
            // Infinitivo a propósito: "Elegí/Elige" obligaría a un override de
            // voseo en es_AR solo por este placeholder.
            'currency_placeholder' => 'Seleccionar moneda',
        ],

        'active_title' => 'País activo',
        'active_desc' => 'Disponible para elegir en direcciones y datos fiscales.',
    ],

    'social_network' => [
        'search_placeholder' => 'Buscar por nombre o abreviatura',
        'search_label' => 'Buscar red social',
        'singular' => 'red social',
        'plural' => 'redes sociales',
        'create' => 'Crear red social',
        'new' => 'Nueva',
        'new_title' => 'Nueva red social',
        'edit_title' => 'Editar',
        'empty' => 'No hay redes sociales que coincidan con la búsqueda.',

        'columns' => [
            'name' => 'Nombre',
            'abbreviation' => 'Abrev.',
            'url' => 'URL',
            'icon' => 'Ícono',
            'status' => 'Estado',
        ],

        'status' => [
            'active' => 'Activa',
            'inactive' => 'Inactiva',
        ],

        'fields' => [
            'name' => 'Nombre',
            'name_placeholder' => 'Ej. Instagram',
            'abbreviation' => 'Abreviatura',
            'abbreviation_hint' => 'Corta: IG, FB, WA',
            'url' => 'URL base',
            'url_hint' => 'Con https://, la página principal de la red',
            'icon' => 'Ícono',
            // Infinitivo a propósito: "Elegí/Elige" obligaría a un override de
            // voseo en es_AR solo por este placeholder.
            'icon_placeholder' => 'Seleccionar ícono',
            'icon_hint' => 'Glifos disponibles en el sistema',
        ],

        'active_title' => 'Red activa',
        'active_desc' => 'Disponible para elegir en los datos de contacto del negocio.',
    ],

];
