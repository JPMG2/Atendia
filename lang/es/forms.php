<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Formularios — copy de los componentes de campo
|--------------------------------------------------------------------------
|
| Textos que emiten los propios componentes `<x-inputsform.*>`, no una pantalla
| concreta. Base NEUTRA (tuteo): hoy no hay verbos en segunda persona, así que
| `es_AR` no necesita override de voseo.
|
*/

return [

    'password' => [
        'show' => 'Mostrar la contraseña',
        'hide' => 'Ocultar la contraseña',
        'caps' => 'Bloq Mayús está activado',
    ],

    'email' => [
        'suggest' => '¿Quisiste decir',
    ],

    'combobox' => [
        'empty' => 'No hay resultados para esa búsqueda.',
        'loading' => 'Cargando opciones…',
        'clear' => 'Limpiar la selección',
    ],

    'datepicker' => [
        'clear' => 'Limpiar la fecha',
    ],

    'file' => [
        'upload' => 'Subir archivo',
        'remove' => 'Quitar el archivo',
    ],

    'phone' => [
        'country' => 'País del número',
    ],

    'avatar' => [
        'pick' => 'Elegir foto',
        'remove' => 'Quitar',
        'crop_hint' => 'Arrastra y acerca la foto hasta encuadrarla en el círculo.',
        'cancel' => 'Cancelar',
        'apply' => 'Usar esta foto',
    ],

];
