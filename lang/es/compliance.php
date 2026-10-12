<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Cumplimiento (panel admin): el historial de cada negocio
|--------------------------------------------------------------------------
|
| Lo que le pasó a un negocio y lo que se hizo al respecto, en orden. La
| respuesta (una suspensión levantada, un pago regularizado) vive en el mismo
| historial que la falta.
*/

return [
    'title' => 'Cumplimiento',
    'sub' => 'El historial de cada negocio: lo que pasó y lo que se hizo al respecto, en orden.',
    'read_at' => 'Al :time',
    'count' => '{1} 1 negocio con historial|[0,*] :count negocios con historial',

    'search' => 'Buscar negocio',
    'search_placeholder' => 'Nombre del negocio',
    'filter' => 'Situación',
    'no_match' => 'Ningún negocio coincide con eso.',
    'empty_title' => 'Todavía no hay nada en ningún historial',
    'empty_body' => 'Cuando un negocio tenga un archivo rechazado, una suspensión o un pago atrasado, su historial aparece acá.',
    'open' => 'Ver historial',
    'close' => 'Cerrar',
    'by' => 'por :name',

    'filters' => [
        'all' => 'Todos',
        'suspended' => 'Suspendidos',
        'debt' => 'Con pago atrasado',
        'recent' => 'Con hechos en los últimos 90 días',
    ],

    'standing' => [
        'suspended' => 'Suspendido',
        'debt' => 'Pago atrasado',
        'observed' => 'Con observaciones',
        'clear' => 'En regla',
    ],

    'standing_hint' => [
        'suspended' => 'El asistente está en silencio hasta que lo levantes.',
        'debt' => 'Pasó la fecha de pago: está en gracia o pausado.',
        'observed' => 'Tuvo hechos en los últimos 90 días y hoy no tiene nada abierto.',
        'clear' => 'Tiene historial, pero nada de los últimos 90 días ni nada abierto.',
    ],

    'table' => [
        'business' => 'Negocio',
        'standing' => 'Hoy',
        'recent' => 'Últimos :days días',
        'total' => 'En total',
        'last' => 'Lo último',
    ],

    'panel' => [
        'subtitle' => '{1} 1 hecho en el historial|[0,*] :count hechos en el historial',
        'empty' => 'Este negocio no tiene nada en su historial.',
    ],

    // Where a catch came from and what it was: the file stores the codes, this is how they read.
    'codes' => [
        'logo' => 'Logo',
        'avatar' => 'Foto de perfil',
        'receipt' => 'Comprobante',
        'profile' => 'Perfil',
        'service' => 'Servicio',
        'product' => 'Producto',
        'faq' => 'Pregunta frecuente',
        'repeat' => 'Varios archivos rechazados seguidos',
        'sexual' => 'Contenido sexual',
        'sexual_minors' => 'Contenido sexual con menores',
        'violence' => 'Violencia',
        'violence_graphic' => 'Violencia gráfica',
        'harassment' => 'Acoso',
        'harassment_threatening' => 'Amenazas',
        'hate' => 'Odio',
        'hate_threatening' => 'Odio con amenazas',
        'illicit' => 'Actividad ilícita',
        'illicit_violent' => 'Actividad ilícita violenta',
        'self-harm' => 'Autolesión',
        'self-harm_intent' => 'Intención de autolesión',
        'self-harm_instructions' => 'Instrucciones de autolesión',
    ],

    'kinds' => [
        'content_rejected' => 'Archivo rechazado por moderación',
        'content_severe' => 'Contenido grave: suspendió el negocio',
        'suspended' => 'Negocio suspendido',
        'appealed' => 'El negocio apeló la suspensión',
        'suspension_lifted' => 'Suspensión levantada',
        'payment_rejected' => 'Comprobante de pago rechazado',
        'payment_late' => 'Pago atrasado: entró en gracia',
        'payment_paused' => 'Asistente pausado por falta de pago',
        'payment_regularized' => 'Pago regularizado',
    ],
];
