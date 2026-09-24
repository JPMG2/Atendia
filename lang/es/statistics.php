<?php

declare(strict_types=1);

// Pantalla "Mis estadísticas": molde de GBP "Rendimiento". Cada bloque trae
// su LECTURA en una frase; la profundidad depende del plan.
return [
    'title' => 'Mis estadísticas',
    'sub' => 'Lo que tu asistente atendió y lo que tus clientes más piden.',

    'since' => 'Desde el :date, tu asistente respondió :questions consultas en :conversations conversaciones.',
    'gathering' => 'Tu asistente todavía está juntando datos. Vuelve en unos días.',

    'kpis' => [
        'conversations' => 'Conversaciones del mes',
        'resolution' => 'Resueltas por tu asistente',
        'new_contacts' => 'Clientes nuevos',
        'questions' => 'Consultas del mes',
        'audio_minutes' => 'Minutos de audio',
        'recovered' => 'Clientes recuperados',
    ],

    'daily' => [
        'title' => 'Conversaciones por día',
        'insight' => 'Tu mejor día fue el :day, con :count conversaciones.',
    ],

    'topics' => [
        'title' => 'Lo que te preguntan, por tema',
        'insight' => 'Tu tema más consultado del mes es «:topic»: tu asistente respondió solo el :share%.',
        'col_topic' => 'Tema',
        'col_asked' => 'Consultas',
        'col_alone' => 'Respondió solo tu asistente',
        'asked' => '{1}1 consulta|[2,*]:count consultas',
        'teach' => 'Enseñar',
        'add_catalog' => 'Agregar al catálogo',
        'by' => [
            'assistant' => 'Respondió tu asistente',
            'team' => 'Respondió tu equipo',
            'nobody' => 'Sin respuesta',
        ],
    ],

    'hours' => [
        'title' => 'Tus horas pico',
        'insight' => 'El :share% de tus consultas llega entre las :from:00 y las :to:00.',
    ],

    'trend' => [
        'title' => 'Tendencia de 6 meses',
        'insight' => 'Llevas :count conversaciones este mes; el pasado cerraste con :previous.',
    ],

    'gaps' => [
        'title' => 'Oportunidades: te piden cosas fuera de tu catálogo',
        'line' => '{1}1 consulta del mes fue por «:sample», y no está en tu catálogo.|[2,*]:count consultas del mes fueron por «:sample», y no está en tu catálogo.',
        'none' => 'Todo lo que te pidieron este mes está en tu catálogo.',
    ],

    'locked_in' => 'Disponible en el plan :plan',
    'see_plans' => 'Ver los planes',
];
