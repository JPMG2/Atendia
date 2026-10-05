<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Logs del sistema (panel admin)
|--------------------------------------------------------------------------
|
| La ventana que muestra los logs tal cual: cada entrada se copia entera,
| lista para pegarla en una conversación de ayuda. Base neutra (tuteo).
|
*/

return [
    'title' => 'Logs del sistema',
    'subtitle' => 'Lo último que registró la plataforma, listo para copiar y pegar cuando algo falla.',

    'refresh' => 'Actualizar',
    'copy' => 'Copiar la entrada con su contexto',
    'copy_all' => 'Copiar todo',
    'copied' => 'Copiado',
    'repeats' => 'se repitió :count vez|se repitió :count veces',
    'since' => 'La primera fue a las :time',

    'context' => [
        'title' => 'Contexto',
        'app' => 'App',
        'environment' => 'Entorno',
        'versions' => 'Versiones',
        'file' => 'Archivo',
        'copied_at' => 'Copiado el',
    ],
    'copied' => 'Copiada',
    'expand' => 'Ver el detalle',
    'showing' => 'Últimas :count entradas de :file',
    'empty' => 'No hay entradas registradas en este archivo.',

    'levels' => [
        'all' => 'Todos',
        'error' => 'Errores',
        'warning' => 'Advertencias',
        'info' => 'Información',
    ],
];
