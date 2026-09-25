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

    // Cambio de plan: subir aplica hoy pagando la diferencia de los días que
    // quedan; bajar se programa para el fin del período pagado (sin devoluciones).
    'change' => [
        'choose' => 'Elegir :plan',
        'up' => 'Subir a :plan',
        'down' => 'Cambiar a :plan',
        'trial_title' => '¿Seguir con :plan al terminar la prueba?',
        'trial_body' => 'Tu prueba sigue igual hasta el :date. Desde ese día tu plan es :plan, a USD :price por mes.',
        'up_title' => '¿Subir a :plan?',
        'up_body' => ':plan empieza hoy con todos sus cupos. Pagas USD :amount hoy por los :days días que quedan de tu período. Tu renovación del :date sigue igual, a USD :price.',
        'down_title' => '¿Cambiar a :plan?',
        'down_body' => 'Sigues en :current con todo lo que pagaste hasta el :date. Desde ese día tu plan es :plan, a USD :price. Puedes cancelar el cambio antes de esa fecha.',
        'down_accept' => 'Programar el cambio',
        'scheduled' => 'Tu plan cambia a :plan el :date.',
        'scheduled_badge' => 'Desde el :date',
        'cancel' => 'Cancelar el cambio',
        'cancelled' => 'Listo: sigues en :plan.',
        'upgraded' => ':plan ya está activo. Sube el comprobante de USD :amount para confirmarlo.',
        'upload' => 'Subir comprobante',
    ],

    'features' => [
        'conversations' => ':cap conversaciones con IA por mes',
        'numbers' => '{1} :cap número de WhatsApp|[2,*] :cap números de WhatsApp',
        'pace' => 'Hasta :cap mensajes por hora por contacto',
        'audio_none' => 'Atención por mensajes de texto',
        'audio' => ':cap minutos de audio transcriptos',
        'ask' => 'Pregúntale a AtendIa: :cap consultas al mes',
        'statistics' => [
            'counts' => 'Estadísticas del negocio',
            'patterns' => 'Estadísticas con temas y conversaciones por día',
            'trends' => 'Analítica avanzada: horas pico, tendencia y lo que piden fuera del catálogo',
        ],
    ],
];
