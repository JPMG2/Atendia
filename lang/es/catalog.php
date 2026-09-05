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

    // Chrome del hub de catálogos (la pantalla que lista los maestros).
    'hub' => [
        'page_title' => 'Catálogos del sistema',
        'title' => 'Configuración general',
        'subtitle' => 'Elige un catálogo de la izquierda y configúralo a la derecha.',
        'rail_label' => 'Catálogos',
        'search_placeholder' => 'Busca un catálogo',
        'search_label' => 'Buscar catálogo',
        'no_matches' => 'Ningún catálogo coincide con la búsqueda.',
        'none' => 'No hay catálogos disponibles.',
        'close' => 'Cerrar catálogo',

        // Estado vacío: es lo primero que ve alguien que entra por primera vez,
        // así que explica QUÉ es un catálogo y PARA QUÉ sirve, en vez de repetir
        // la instrucción del encabezado.
        'empty_title' => 'Elige un catálogo para empezar',
        'empty_body' => 'Los catálogos son las listas base del sistema: países, monedas, condiciones fiscales, estados. Lo que definas aquí es lo que después vas a poder elegir en el resto de AtendIa.',
    ],

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
            'iso2' => 'ISO-2',
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
            'iso2' => 'Código ISO-2',
            'iso2_hint' => '2 letras (AR, US)',
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
        'search_placeholder' => 'Buscar por nombre, provincia o país',
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
            'country' => 'País',
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
            'color' => 'Color',
        ],

        'fields' => [
            'name' => 'Nombre',
            'name_placeholder' => 'Ej. En proceso',
            'color' => 'Color',
            'color_placeholder' => 'Seleccionar color',
            'color_hint' => 'Con el que se pinta este estado en todo el sistema',
        ],

        // La paleta es semántica: el nombre dice para QUÉ sirve el color, no solo
        // qué tono es. Espeja CurrentStatus::COLORS.
        'colors' => [
            'success' => 'Verde (todo bien)',
            'info' => 'Azul (informativo)',
            'warning' => 'Ámbar (atención)',
            'danger' => 'Rojo (problema)',
            'brand' => 'Jade (marca)',
            'neutral' => 'Gris (sin relevancia)',
        ],
    ],

    // --- Grupo Negocio: lo que el negocio elige al configurarse ---

    'service_type' => [
        'search_placeholder' => 'Buscar por clave, nombre, modalidad o atributo',
        'search_label' => 'Buscar tipo de servicio',
        'singular' => 'tipo de servicio',
        'plural' => 'tipos de servicio',
        'create' => 'Crear tipo',
        'new' => 'Nuevo',
        'new_title' => 'Nuevo tipo de servicio',
        'edit_title' => 'Editar',
        'empty' => 'No hay tipos de servicio que coincidan con la búsqueda.',

        'columns' => [
            'code' => 'Clave',
            'name' => 'Tipo de servicio',
            'modality' => 'Modalidad',
            'sector' => 'Rubro',
            'attributes' => 'Atributos',
            'status' => 'Estado',
        ],

        'status' => [
            'active' => 'Activo',
            'inactive' => 'Inactivo',
        ],

        'fields' => [
            'code' => 'Clave',
            'code_hint' => 'Sin espacios ni acentos: consulta, pedido-llevar',
            'name' => 'Nombre',
            'name_placeholder' => 'Ej. Consulta',
            'modality' => 'Modalidad',
            'modality_placeholder' => 'Elegí cómo se ofrece',
            'modality_hint' => 'Una sola: decide qué pregunta el asistente',
            'description' => 'Descripción',
            'description_placeholder' => 'Ej. Atención con turno, uno a la vez',
            'description_hint' => 'Ayuda al negocio a saber si es lo que ofrece',
            'sector' => 'Rubro',
            'sector_placeholder' => 'Sin rubro',
            'sector_hint' => 'Solo agrupa esta pantalla; a quién se le ofrece lo deciden las actividades',
            'order' => 'Orden',
            'order_hint' => 'En qué posición se le sugiere al negocio',
            'status' => 'Estado',
        ],
    ],

    'service_modality' => [
        'search_placeholder' => 'Buscar por clave, nombre o descripción',
        'search_label' => 'Buscar modalidad',
        'singular' => 'modalidad',
        'plural' => 'modalidades',
        'create' => 'Crear modalidad',
        'new' => 'Nueva',
        'new_title' => 'Nueva modalidad',
        'edit_title' => 'Editar',
        'empty' => 'No hay modalidades que coincidan con la búsqueda.',

        'columns' => [
            'code' => 'Clave',
            'name' => 'Nombre',
            'description' => 'Qué pide y qué recuerda',
            'order' => 'Orden',
            'status' => 'Estado',
        ],

        'status' => [
            'active' => 'Activa',
            'inactive' => 'Inactiva',
        ],

        'fields' => [
            'code' => 'Clave',
            'code_hint' => 'Es a lo que se engancha el sistema: cita, reserva, alquiler',
            'name' => 'Nombre',
            'name_placeholder' => 'Ej. Cita / Turno',
            'description' => 'Descripción',
            'description_placeholder' => 'Ej. Fecha, hora y duración con un profesional',
            'description_hint' => 'Qué le pregunta el asistente y qué recuerda el sistema',
            'icon' => 'Ícono',
            'icon_placeholder' => 'Elegí un glifo',
            'icon_hint' => 'Con el que se muestra en el chip de la modalidad',
            'order' => 'Orden',
            'order_hint' => 'En qué posición se le ofrece al negocio',
            'status' => 'Estado',
        ],
    ],

    'service_attribute' => [
        'search_placeholder' => 'Buscar por clave, nombre o tipo',
        'search_label' => 'Buscar atributo',
        'singular' => 'atributo',
        'plural' => 'atributos',
        'create' => 'Crear atributo',
        'new' => 'Nuevo',
        'new_title' => 'Nuevo atributo',
        'edit_title' => 'Editar',
        'empty' => 'No hay atributos que coincidan con la búsqueda.',

        'columns' => [
            'code' => 'Clave',
            'name' => 'Nombre',
            'type' => 'Tipo de dato',
            'description' => 'Descripción',
            'options' => 'Opciones',
            'status' => 'Estado',
        ],

        'status' => [
            'active' => 'Activo',
            'inactive' => 'Inactivo',
        ],

        // Cardinalidad: "Obra social" no es una sola (OSDE, Swiss Medical…).
        'multiple' => [
            'on' => 'Varios',
            'off' => 'Uno',
        ],

        'fields' => [
            'code' => 'Clave',
            'code_hint' => 'Sin espacios ni acentos: obra_social, apto_celiaco',
            'name' => 'Nombre',
            'name_placeholder' => 'Ej. Duración',
            'data_type' => 'Tipo de dato',
            'data_type_placeholder' => 'Elegí el tipo',
            'data_type_hint' => 'Decide con qué campo se carga el valor',
            'description' => 'Descripción',
            'description_placeholder' => 'Ej. Cuánto lleva la atención',
            'description_hint' => 'Ayuda al negocio a completarlo bien',
            'unit' => 'Unidad',
            'unit_placeholder' => 'Ej. min',
            'unit_hint' => 'Se muestra junto al valor',
            'multiple' => 'Valores',
            'options' => 'Opciones de la lista',
            'options_placeholder' => 'Ej. Chico, Mediano, Grande',
            'options_hint' => 'Separadas por coma. Solo se usan si el tipo de dato es una lista.',
            'order' => 'Orden',
            'order_hint' => 'En qué posición se ofrece al armar un tipo de servicio',
            'status' => 'Estado',
        ],
    ],

    'business_sector' => [
        'search_placeholder' => 'Buscar por clave o nombre',
        'search_label' => 'Buscar rubro',
        'singular' => 'rubro',
        'plural' => 'rubros',
        'create' => 'Crear rubro',
        'new' => 'Nuevo',
        'new_title' => 'Nuevo rubro',
        'edit_title' => 'Editar',
        'empty' => 'No hay rubros que coincidan con la búsqueda.',

        'columns' => [
            'code' => 'Clave',
            'name' => 'Nombre',
            'description' => 'Descripción',
            'order' => 'Orden',
            'status' => 'Estado',
        ],

        'status' => [
            'active' => 'Activo',
            'inactive' => 'Inactivo',
        ],

        'fields' => [
            'code' => 'Clave',
            'code_hint' => 'Sin espacios ni acentos: salud, gastronomia',
            'name' => 'Nombre',
            'name_placeholder' => 'Ej. Gastronomía',
            'description' => 'Descripción',
            'description_placeholder' => 'Ej. Comida y bebida, para el local o para llevar',
            'description_hint' => 'Ayuda al negocio a elegir bien su rubro',
            'order' => 'Orden',
            'order_hint' => 'En qué posición se le ofrece al negocio',
            'status' => 'Estado',
        ],
    ],

    'business_activity' => [
        'search_placeholder' => 'Buscar por clave, nombre o rubro',
        'search_label' => 'Buscar actividad',
        'singular' => 'actividad',
        'plural' => 'actividades',
        'create' => 'Crear actividad',
        'new' => 'Nueva',
        'new_title' => 'Nueva actividad',
        'edit_title' => 'Editar',
        'empty' => 'No hay actividades que coincidan con la búsqueda.',

        'columns' => [
            'code' => 'Clave',
            'name' => 'Nombre',
            'sector' => 'Rubro',
            'order' => 'Orden',
            'status' => 'Estado',
        ],

        'status' => [
            'active' => 'Activa',
            'inactive' => 'Inactiva',
        ],

        'fields' => [
            'code' => 'Clave',
            'code_hint' => 'Sin espacios ni acentos: farmacia, panaderia',
            'name' => 'Nombre',
            'name_placeholder' => 'Ej. Panadería',
            'sector' => 'Rubro',
            'sector_placeholder' => 'Elegir rubro',
            'description' => 'Descripción',
            'description_placeholder' => 'Ej. Elaboración y venta de pan y facturas',
            'description_hint' => 'Con qué palabras el negocio se reconoce en esta actividad',
            'order' => 'Orden',
            'order_hint' => 'En qué posición se ofrece dentro del rubro',
            'status' => 'Estado',
        ],
    ],

];
