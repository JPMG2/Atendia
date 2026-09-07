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
            'description_hint' => 'Tu asistente la usa para presentarse. Contá qué hacés y qué te hace distinto.',
        ],
        'location' => [
            'sub' => 'Si atendés en un local, tu asistente puede pasar la dirección cuando se la pidan.',
            'question' => '¿Atendés clientes en un local?',
        ],
        'billing' => [
            'natural_hint' => '¿No tenés datos fiscales? Sin problema: tu factura sale a tu nombre, como persona natural.',
        ],
        'preview' => [
            'caption' => 'La descripción y el logo salen acá.',
        ],
    ],
];
