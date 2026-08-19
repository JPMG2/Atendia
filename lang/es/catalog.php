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
        // Default del <x-inputsform.switch-field>. Cada maestro pasa su propio
        // par para que concuerde el género ("Activa" en moneda, "Activo" en país).
        'on' => 'Activo',
        'off' => 'Inactivo',
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
            'status' => 'Estado',
        ],
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
            'status' => 'Estado',
        ],
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
            'status' => 'Estado',
        ],
    ],

    'province' => [
        'search_placeholder' => 'Buscar por nombre o país',
        'search_label' => 'Buscar provincia',
        'singular' => 'provincia',
        'plural' => 'provincias',
        'create' => 'Crear provincia',
        'new' => 'Nueva',
        'new_title' => 'Nueva provincia',
        'edit_title' => 'Editar',
        'empty' => 'No hay provincias que coincidan con la búsqueda.',

        'columns' => [
            'name' => 'Nombre',
            'country' => 'País',
            'status' => 'Estado',
        ],

        'status' => [
            'active' => 'Activa',
            'inactive' => 'Inactiva',
        ],

        'fields' => [
            'name' => 'Nombre',
            'name_placeholder' => 'Ej. Buenos Aires',
            'country' => 'País',
            'country_placeholder' => 'Seleccionar país',
            'status' => 'Estado',
        ],
    ],

    'region' => [
        'search_placeholder' => 'Buscar por nombre o provincia',
        'search_label' => 'Buscar región',
        'singular' => 'región',
        'plural' => 'regiones',
        'create' => 'Crear región',
        'new' => 'Nueva',
        'new_title' => 'Nueva región',
        'edit_title' => 'Editar',
        'empty' => 'No hay regiones que coincidan con la búsqueda.',

        'columns' => [
            'name' => 'Nombre',
            'province' => 'Provincia',
            'status' => 'Estado',
        ],

        'status' => [
            'active' => 'Activa',
            'inactive' => 'Inactiva',
        ],

        'fields' => [
            'name' => 'Nombre',
            'name_placeholder' => 'Ej. Zona Norte',
            'province' => 'Provincia',
            'province_placeholder' => 'Seleccionar provincia',
            'status' => 'Estado',
        ],
    ],

    'tax_condition' => [
        'search_placeholder' => 'Buscar por código o nombre',
        'search_label' => 'Buscar condición fiscal',
        'singular' => 'condición fiscal',
        'plural' => 'condiciones fiscales',
        'create' => 'Crear condición',
        'new' => 'Nueva',
        'new_title' => 'Nueva condición fiscal',
        'edit_title' => 'Editar',
        'empty' => 'No hay condiciones fiscales que coincidan con la búsqueda.',

        'columns' => [
            'code' => 'Código',
            'name' => 'Nombre',
            'country' => 'País',
            'discriminate_tax' => 'Discrimina',
            'status' => 'Estado',
        ],

        'status' => [
            'active' => 'Activa',
            'inactive' => 'Inactiva',
        ],

        // Sí/No para el switch de discriminación: no es un estado de alta/baja,
        // es una característica de la condición fiscal.
        'discriminate' => [
            'yes' => 'Sí',
            'no' => 'No',
        ],

        'fields' => [
            'code' => 'Código',
            'code_hint' => 'Corto: RI, MT, EX',
            'name' => 'Nombre',
            'name_placeholder' => 'Ej. Responsable Inscripto',
            'country' => 'País',
            'country_placeholder' => 'Seleccionar país',
            'discriminate_tax' => 'Discrimina impuesto',
            'status' => 'Estado',
        ],
    ],

    'status' => [
        'search_placeholder' => 'Buscar por nombre',
        'search_label' => 'Buscar estado',
        'singular' => 'estado',
        'plural' => 'estados',
        'create' => 'Crear estado',
        'new' => 'Nuevo',
        'new_title' => 'Nuevo estado',
        'edit_title' => 'Editar',
        'empty' => 'No hay estados que coincidan con la búsqueda.',

        'columns' => [
            'name' => 'Nombre',
        ],

        'fields' => [
            'name' => 'Nombre',
            'name_placeholder' => 'Ej. En proceso',
        ],
    ],

];
