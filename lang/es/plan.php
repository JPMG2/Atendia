<?php

declare(strict_types=1);

// Pantalla "Mi plan" del panel cliente: el plan vigente, sus medidores de
// uso y la escalera de planes con lo bloqueado a la vista (candado).
return [
    'title' => 'Mi plan',
    'sub' => 'Lo que incluye tu plan y cuánto usaste este mes.',

    'current' => 'Tu plan actual',
    'trial_badge' => 'Prueba gratis: te quedan :days días',
    'trial_over' => 'Tu prueba terminó: sigues en el plan :plan',
    'per_month' => '/mes',

    'meters' => [
        'title' => 'Tu uso este mes',
        'conversations' => 'Conversaciones con la IA',
        'conversations_of' => ':used de :cap',
        'audio' => 'Minutos de audio transcriptos',
        'audio_of' => ':used de :cap min',
        'numbers' => 'Números de WhatsApp',
        'numbers_of' => ':used de :cap',
        'pace' => 'Ritmo máximo por contacto',
        'pace_value' => ':cap mensajes por hora',
    ],

    'names' => [
        'emprende' => 'Emprende',
        'negocio' => 'Negocio',
        'premium' => 'Premium',
    ],

    'ladder_title' => 'Todos los planes',
    'yours' => 'Tu plan',
    'popular' => 'Más elegido',
    'locked_in' => 'Disponible en :plan',
    'cta' => 'Quiero este plan',
    'cta_text' => 'Hola, quiero pasar mi negocio al plan :plan de AtendIa.',
    'cta_soon' => 'Muy pronto podrás cambiar de plan desde aquí.',

    'features' => [
        'conversations' => ':cap conversaciones con IA por mes',
        'numbers' => '1 número de WhatsApp|:cap números de WhatsApp',
        'pace' => 'Hasta :cap mensajes por hora por contacto',
        'audio_none' => 'Atención por mensajes de texto',
        'audio' => ':cap minutos de audio transcriptos',
    ],
];
