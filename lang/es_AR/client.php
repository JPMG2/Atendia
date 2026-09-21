<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Panel del cliente — overrides de voseo (parcial, cae a lang/es)
|--------------------------------------------------------------------------
*/

return [
    'setup' => [
        'sub' => 'Completá estos pasos y tu negocio responde solo, a toda hora.',
        'steps' => [
            'business' => [
                'label' => 'Completá los datos de tu negocio',
            ],
            'catalog' => [
                'label' => 'Cargá tus servicios o productos',
            ],
            'try' => [
                'label' => 'Probá tu asistente',
                'hint' => 'Escribile como si fueras un cliente y miralo responder.',
            ],
            'whatsapp' => [
                'label' => 'Conectá tu WhatsApp',
                'hint' => 'Desde ese momento responde por vos las 24 horas.',
            ],
        ],
    ],

    'business' => [
        'try' => [
            'button' => 'Probalo ahora',
        ],
        'identity' => [
            'name_hint' => 'Escribilo tal como aparece en tu marca: así lo va a mostrar WhatsApp.',
            'description_hint' => 'Tu asistente la usa para presentarse. Contá qué hacés y qué te hace distinto.',
            'ai_body' => 'Decinos tres palabras clave y nuestra inteligencia artificial redacta la presentación por vos.',
        ],
        'location' => [
            'sub' => 'Si atendés en un local, tu asistente puede pasar la dirección cuando se la pidan.',
            'question' => '¿Atendés clientes en un local?',
        ],
        'social' => [
            'network_placeholder' => 'Elegí una red',
        ],
        'billing' => [
            'natural_hint' => '¿No tenés datos fiscales? Sin problema: tu factura sale a tu nombre, como persona natural.',
        ],
        'preview' => [
            'caption' => 'La descripción y el logo salen acá.',
        ],
    ],

    'services' => [
        'sub' => 'Lo que ofrecés, tal como tu asistente lo cuenta cuando se lo preguntan.',
        'search_placeholder' => 'Buscá por nombre',
        'no_results' => 'Nada coincide con tu búsqueda. Probá con otro nombre.',
        'ai_title' => '¿No sabés cómo redactar las descripciones?',
        'ai_body' => 'Nuestra inteligencia artificial puede escribir por vos textos claros y atractivos para tus servicios.',
        'sheet_hint' => 'Todo lo que cargues acá, tu asistente lo responde en WhatsApp.',
        // "Adelanto" is the neutral base; Argentina says "seña".
        'field_deposit' => 'Seña',
        'deposit_short' => 'seña $ :amount',
    ],

    'products' => [
        'sub' => 'Lo que vendés; tu asistente responde "¿tienen tal cosa?" con esta lista.',
        'search_placeholder' => 'Buscá por nombre o código',
        'no_results' => 'Nada coincide con tu búsqueda. Probá con otro nombre o código.',
        'empty_body' => 'Subí tu Excel y tu asistente aprende el catálogo completo en minutos.',
        'empty_or' => 'O escribilos de a uno cuando quieras.',
        'import_body' => 'Subí la planilla que ya usás: leemos las columnas y tu asistente aprende cada fila.',
        'ai_body' => 'Enviala tal como la tenés: nuestra inteligencia artificial ordena nombres y precios por vos en segundos.',
    ],

    'simulator' => [
        'empty' => 'Cargá tus servicios o productos y mirá cómo responde tu asistente.',
    ],

    'conversations' => [
        'empty_body' => 'Cuando tu asistente atienda el WhatsApp, acá vas a ver cada conversación.',
        'taught_list_title' => 'Lo que aprendió acá',
        'select' => 'Elegí una conversación para leerla.',
        'read_only' => 'Solo lectura: tu asistente responde por vos.',
        'reply_placeholder' => 'Escribí tu respuesta…',
    ],

    'assistant' => [
        'sub' => 'Todo lo que usa para responder, a la vista. Enseñale lo que tu catálogo no dice.',
        'misses_sub' => 'Tus clientes preguntaron esto y tu asistente no tuvo la respuesta. Enseñásela y no vuelve a pasar.',
        'no_drafts' => 'Tu conocimiento no tiene base para sugerir estas. Respondelas vos.',
        'sources_sub' => 'Estas fuentes se indexan automáticamente cada vez que las cambiás.',
        'faq_title' => 'Lo que le enseñás vos',
        'faq_sub' => 'Preguntas y respuestas escritas por vos. Tu asistente las responde al minuto.',
        'faq_empty_body' => '¿Aceptan tarjeta? ¿Necesito ayuno? ¿Hacen envíos? Escribí la respuesta una vez y tu asistente la repite siempre.',
        'answer_hint' => 'Escribila como se la dirías a un cliente; tu asistente la usa tal cual.',
        'try_failed' => 'No pudimos probarla ahora. Intentá de nuevo en un momento.',
    ],

    'customers' => [
        'empty_body' => 'Cuando tu asistente atienda el WhatsApp, cada persona que escriba va a aparecer acá con su ficha.',
        'opt_in_unavailable' => 'Conectá tu WhatsApp para pedir permiso.',
        'opt_in_message' => 'Hola:name 👋 Somos :business. ¿Te gustaría recibir por acá nuestras ofertas y novedades? Respondé "sí" y listo. Podés pedir salir cuando quieras.',
    ],
];
