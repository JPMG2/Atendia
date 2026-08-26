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

    'combobox' => [
        'empty' => 'No hay resultados para esa búsqueda.',
        'loading' => 'Cargando opciones…',
        'clear' => 'Limpiar la selección',
    ],

];
