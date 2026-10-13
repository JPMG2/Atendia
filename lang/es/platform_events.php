<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Avisos de la plataforma — lo que el admin tiene que saber
|--------------------------------------------------------------------------
| Voz del panel admin: operativa y seca, le habla a una sola persona. Cada
| tipo trae su "qué hacer": un aviso que no dice qué sigue es solo ruido.
*/

return [
    'severity' => [
        'critical' => 'Crítico',
        'important' => 'Importante',
        'info' => 'Informativo',
    ],

    'state' => [
        'open' => 'Sin ver',
        'acknowledged' => 'Visto',
        'resolved' => 'Resuelto',
    ],

    'area' => [
        'customers' => 'Clientes',
        'money' => 'Dinero',
        'compliance' => 'Cumplimiento',
        'system' => 'Sistema',
    ],

    'kinds' => [
        'business_joined' => [
            'label' => 'Cliente nuevo',
            'hint' => 'Escríbele hoy: un primer contacto el mismo día cambia cómo empieza.',
        ],
        'whatsapp_disconnected' => [
            'label' => 'WhatsApp desconectado',
            'hint' => 'Su asistente no responde hasta que vuelva a escanear el código.',
        ],
        'receipt_received' => [
            'label' => 'Comprobante por verificar',
            'hint' => 'El negocio espera que lo apruebes o lo rechaces.',
        ],
        'payment_late' => [
            'label' => 'Pago atrasado',
            'hint' => 'Está en días de gracia: si no paga, se pausa su asistente.',
        ],
        'payment_paused' => [
            'label' => 'Asistente pausado por falta de pago',
            'hint' => 'Su asistente dejó de responder. Escríbele para regularizar.',
        ],
        'business_suspended' => [
            'label' => 'Negocio suspendido',
            'hint' => 'Su asistente está callado y los envíos cortados. Revisa el motivo y decide.',
        ],
        'appeal_received' => [
            'label' => 'Apelación recibida',
            'hint' => 'Un negocio suspendido pidió que lo revises.',
        ],
        'content_to_review' => [
            'label' => 'Contenido por revisar',
            'hint' => 'Una imagen o un texto quedó rechazado y espera tu revisión.',
        ],
    ],
];
