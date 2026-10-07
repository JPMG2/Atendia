<?php

declare(strict_types=1);

return [
    'title' => 'Calidad de la IA',
    'sub' => 'Qué respuestas se marcaron mal. Las dos IA por separado: la que atiende a los clientes y la que te contesta a vos.',
    'read_at' => 'Al :time',

    'customer' => [
        'title' => 'La IA que atiende a tus clientes',
        'empty' => 'Todavía nadie marcó una respuesta. No significa que estén bien: significa que nadie las marcó.',
    ],

    'owner' => [
        'title' => 'Pregúntale a :brand (la tuya)',
        'empty' => 'Todavía no marcaste ninguna respuesta de tu asistente.',
    ],

    'score' => [
        'good' => 'Bien',
        'bad' => 'Mal',
        'over' => '{1} sobre 1 respuesta marcada|[2,*] sobre :total respuestas marcadas',
        'none' => 'Sin marcas',
        'warning' => '{1} Es una sola marca: no alcanza para sacar ninguna conclusión. Casi nadie califica, así que este número dice más de quién marcó que de la IA.|[2,*] Son :total marcas en total: muy pocas para sacar una conclusión. Casi nadie califica, así que este número dice más de quién marcó que de la IA.',
    ],

    'table' => [
        'business' => 'Negocio',
        'good' => 'Bien',
        'bad' => 'Mal',
        'total' => 'Marcas',
        'question' => 'Pregunta',
        'answer' => 'Lo que respondió',
        'who' => 'Quién la marcó',
        'when' => 'Cuándo',
    ],

    'unknown' => '—',
    'open_incidents' => 'Ver las marcadas mal',
];
