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
    'copy' => 'Copiar la entrada',
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
