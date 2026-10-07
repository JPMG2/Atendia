<?php

declare(strict_types=1);

return [
    'title' => 'Qué salió mal',
    'sub' => 'Las charlas que quedaron sin atender y los trabajos que se cayeron. Lo más grave primero, no lo más viejo.',
    'read_at' => 'Al :time',
    'pending' => '{0}nada pendiente|{1}1 pendiente|[2,*]:count pendientes',

    'kinds' => [
        'unanswered' => [
            'tab' => 'Sin responder',
            'label' => 'La IA no contestó',
            'help' => 'El cliente escribió y nadie le respondió. Son los que se pierden.',
        ],
        'job_failed' => [
            'tab' => 'Se cayó',
            'label' => 'Trabajo caído',
            'help' => 'Algo falló por detrás. El log tiene el detalle completo.',
        ],
        'handoff_unattended' => [
            'tab' => 'Derivadas',
            'label' => 'Derivada y sin atender',
            'help' => 'Se llamó a una persona y no entró nadie. La IA calla por diseño, así que nadie está contestando.',
        ],
        'customer_repeated' => [
            'tab' => 'Insistieron',
            'label' => 'Preguntó dos veces',
            'help' => 'Volvió a preguntar y nadie le respondió. Insistir es lo que hace alguien justo antes de irse.',
        ],
        'answer_rejected' => [
            'tab' => 'Mal contestadas',
            'label' => 'Respuesta marcada mal',
            'help' => 'El negocio marcó esta respuesta como equivocada. Sigue contestando igual hasta que alguien le enseñe.',
        ],
        'customer_upset' => [
            'tab' => 'Molestos',
            'label' => 'Cliente molesto',
            'help' => 'La charla terminó mal según la lectura del asistente.',
        ],
    ],

    'table' => [
        'what' => 'Qué pasó',
        'business' => 'Negocio',
        'customer' => 'Cliente',
        'waiting' => 'Esperando',
        'last' => 'Lo último que se dijo',
    ],

    'waiting_minutes' => '{1}1 min|[2,*]:count min',
    'waiting_hours' => '{1}1 h|[2,*]:count h',
    'waiting_days' => '{1}1 día|[2,*]:count días',

    'concentrated' => ':business concentra :count de :total. No son :total accidentes: es un negocio fallando.',
    'concentrated_all' => 'Las :count son del mismo negocio: :business.',

    'open_business' => 'Ver el negocio',
    'open_logs' => 'Ver el log',
    'no_business' => 'De la plataforma',

    'mail' => [
        'subject' => '{1} Una charla quedó sin atender|[2,*] :count cosas salieron mal hoy',
        'preheader' => 'Lo que quedó sin atender, lo más grave primero.',
        'eyebrow' => 'Qué salió mal',
        'intro' => 'Esto quedó sin resolver. Lo más grave va primero, no lo más viejo.',
        'line' => ':what — :business · :customer · esperando :waiting',
        'cta' => 'Abrir el escritorio',
        'closing' => 'En un día limpio este correo no llega, así que si llegó, algo hay.',
    ],

    'all_good_title' => 'Nada por atender',
    'all_good_body' => 'Ninguna charla quedó sin responder y ningún trabajo se cayó. Esta pantalla vacía es la buena noticia.',
    'none_of_kind' => 'Nada en esta categoría.',
];
