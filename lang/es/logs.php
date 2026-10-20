<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Logs del sistema (panel admin)
|--------------------------------------------------------------------------
|
| Los problemas que dicen los logs, agrupados: una fila por cosa que se repite,
| con su estado. El detalle se copia entero, listo para pegarlo en una
| conversación de ayuda. Base neutra (tuteo).
|
*/

return [
    'title' => 'Logs del sistema',
    'subtitle' => 'Los problemas que se repiten en la plataforma, con el más reciente primero.',

    'ranges' => [
        'hour' => '1 hora',
        'day' => '24 horas',
        'week' => '7 días',
        'month' => '30 días',
    ],
    'periods' => [
        'hour' => 'en la última hora',
        'day' => 'en las últimas 24 horas',
        'week' => 'en 7 días',
        'month' => 'en 30 días',
    ],

    'read_at' => 'Última lectura de los logs: :date · se actualiza sola',
    'never_title' => 'Todavía no se leyeron los logs',
    'never_body' => 'La primera lectura ocurre en los próximos minutos. Después se actualiza sola.',
    'stale' => 'Los logs no se leen desde el :date. Lo que ves puede estar atrasado.',

    'tiles' => [
        'new' => 'Nuevos hoy',
        'new_foot' => 'Primera vez en las últimas 24 h',
        'new_none' => 'Nada nuevo hoy',
        'worse' => 'Se repiten más',
        'worse_foot' => 'Suben o regresaron',
        'worse_none' => 'Ninguno sube',
        'open' => 'Abiertos',
        'open_foot' => 'Sin resolver ni silenciar, :period',
        'last' => 'Último error',
        'last_foot' => 'Entre los abiertos',
        'last_none' => 'Sin errores abiertos',
    ],

    'tabs' => [
        'active' => 'Activos',
        'resolved' => 'Resueltos',
        'muted' => 'Silenciados',
    ],
    'levels' => [
        'all' => 'Todos',
        'error' => 'Errores',
        'warning' => 'Avisos',
    ],
    'level_one' => [
        'error' => 'Error',
        'warning' => 'Aviso',
    ],
    'tags' => [
        'new' => 'Nuevo',
        'worse' => 'Se repite más',
        'back' => 'Regresó',
    ],
    'columns' => [
        'problem' => 'Problema',
        'level' => 'Nivel',
        'times' => 'Veces',
        'trend' => 'Últimas 24 h',
        'last' => 'Última vez',
    ],
    'since' => 'desde :date',

    'search' => 'Buscar un problema',
    'search_placeholder' => 'Buscar un problema…',
    'count' => '{1}1 problema :period|[0,*]:count problemas :period',
    'grouping_note' => 'Se agrupan los mensajes que solo cambian en números o ids.',
    'clear_filters' => 'Quitar el filtro',

    'actions' => [
        'resolve' => 'Resolver',
        'mute' => 'Silenciar',
        'reopen' => 'Reabrir',
        'unmute' => 'Reactivar',
        'open' => 'Ver el detalle de :problem',
    ],
    'moved' => [
        'resolved' => 'Marcado como resuelto. Si vuelve a ocurrir, regresa a Activos.',
        'muted' => 'Silenciado. Ya no cuenta entre los abiertos.',
        'active' => 'Vuelve a estar activo.',
    ],

    'empty' => [
        'active_title' => 'Nada por atender',
        'active_body' => 'No hay problemas activos :period.',
        'resolved_title' => 'Nada resuelto',
        'resolved_body' => 'Lo que marques como resuelto aparece aquí.',
        'muted_title' => 'Nada silenciado',
        'muted_body' => 'Lo que silencies aparece aquí.',
    ],

    'copy_summary' => 'Copiar resumen para pedir ayuda',
    'copy' => 'Copiar para pedir ayuda',
    'copied' => 'Copiado',
    'summary_line' => ':title — :count veces, última vez :last',

    'panel' => [
        'close' => 'Cerrar',
        'last_day' => 'Últimas 24 h',
        'last_week' => '7 días',
        'last_month' => '30 días',
        'chart' => 'Veces por hora, últimas 24 h',
        'when' => 'Cuándo',
        'when_text' => 'Primera vez :first · última vez :last.',
        'back_note' => 'Estaba resuelto y volvió a ocurrir.',
        'message' => 'Mensaje agrupado',
        'sample' => 'Ejemplo más reciente',
        'sample_gone' => 'El archivo de log ya no conserva el ejemplo. Queda el resumen.',
        'state' => [
            'active' => 'Activo',
            'resolved' => 'Resuelto',
            'muted' => 'Silenciado',
        ],
    ],

    'context' => [
        'title' => 'Contexto',
        'app' => 'App',
        'environment' => 'Entorno',
        'versions' => 'Versiones',
        'file' => 'Archivo',
        'copied_at' => 'Copiado el',
    ],
];
