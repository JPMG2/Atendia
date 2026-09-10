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

    'kpis' => [
        'handoffs' => 'Derivadas a vos',
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
        'add_placeholder' => 'Escribí un servicio, por ejemplo "Corte de dama"',
        'ai_title' => '¿No sabés cómo redactar las descripciones?',
        'ai_body' => 'Nuestra inteligencia artificial puede escribir por vos textos claros y atractivos para tus servicios.',
    ],

    'products' => [
        'sub' => 'Lo que vendés; tu asistente responde "¿tienen tal cosa?" con esta lista.',
        'add_placeholder' => 'Escribí un producto, por ejemplo "Bujía NGK"',
        'empty_body' => 'Subí tu Excel y tu asistente aprende el catálogo completo en minutos.',
        'empty_or' => 'O escribilos de a uno cuando quieras.',
        'import_body' => 'Subí la planilla que ya usás: leemos las columnas y tu asistente aprende cada fila.',
        'ai_body' => 'Enviala tal como la tenés: nuestra inteligencia artificial ordena nombres y precios por vos en segundos.',
    ],

    'simulator' => [
        'empty' => 'Cargá tus servicios o productos y mirá cómo responde tu asistente.',
    ],
];
