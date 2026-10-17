<?php

declare(strict_types=1);

// Venezuela: "cita" instead of "turno"; everything else falls back to lang/es.

return [
    'meta_description' => ':brand conecta tu WhatsApp con un asistente de IA que responde, agenda citas y muestra tu catálogo.',
    'demo' => [
        'rubros' => [
            'peluqueria' => [
                'pool' => [
                    0 => [
                        'side' => 'in',
                        'text' => '¿Tienen cita el sábado a la tarde?',
                    ],
                    1 => [
                        'side' => 'out',
                        'text' => '¡Sí! Me queda 16:30 con Sofi. ¿Te lo reservo? 😊',
                    ],
                    2 => [
                        'side' => 'in',
                        'text' => 'Do you do balayage?',
                    ],
                    3 => [
                        'side' => 'out',
                        'text' => 'Yes! Balayage from $55.000, hydration treatment included ✨',
                    ],
                ],
            ],
            'clinica' => [
                'chips' => [
                    0 => '¿Tienen cita esta semana?',
                    1 => '¿Cuánto sale una ecografía?',
                    2 => 'How much is an ultrasound?',
                ],
            ],
        ],
    ],
    'features' => [
        'schedule' => [
            'title' => 'Agenda citas sola',
        ],
        'alerts' => [
            'body' => 'Recibes un resumen de citas y conversaciones. Intervienes solo cuando hace falta.',
        ],
        'vignettes' => [
            'thread_1_text' => '¿Tienen cita mañana a la tarde?',
            'alert_text' => 'Resumen del día: 8 citas confirmadas, 2 charlas esperando tu respuesta',
        ],
    ],
    'cases' => [
        'health' => [
            'body' => 'Define estudios, duración y capacidad por día. :brand agenda citas, recuerda y reprograma.',
            'tags' => [
                0 => 'Citas',
                1 => 'Recordatorios',
                2 => 'Estudios',
            ],
        ],
    ],
    'faq' => [
        'items' => [
            0 => [
                'q' => '¿Sirve si vendo productos y no doy citas?',
                'a' => 'Sí. Cargas tu catálogo con precio y stock, y el asistente responde qué hay, cuánto sale y cuántas quedan. Cuando alguien quiere comprar, te avisa para que lo cierres tú.',
            ],
            1 => [
                'q' => '¿Qué pasa si me escriben en otro idioma?',
                'a' => 'Responde en el idioma en que le escriben — inglés, portugués o el que sea — con los datos de tu negocio. Los avisos para tu equipo llegan siempre en español.',
            ],
            2 => [
                'q' => '¿Puede decirle algo equivocado a mis clientes?',
                'a' => 'Responde solo con lo que tu negocio cargó: tus servicios, tus precios, tus respuestas. Si algo no está en su conocimiento, lo dice con honestidad y ofrece pasar con tu equipo — nunca inventa.',
            ],
            3 => [
                'q' => '¿Se nota que es un bot?',
                'a' => 'Se presenta como el asistente de tu negocio desde el primer mensaje: nadie es engañado. Escribe con tono cercano, en el idioma del cliente, y tú decides cuándo deriva a una persona.',
            ],
            4 => [
                'q' => '¿Pierdo el control de mi WhatsApp?',
                'a' => 'No: el número sigue siendo tuyo y ves cada conversación en tu panel. Puedes tomar cualquier charla cuando quieras y devolverla al asistente con un clic.',
            ],
            5 => [
                'q' => '¿Qué pasa con los datos de mis clientes?',
                'a' => 'Quedan aislados en tu cuenta, con candado a nivel de base de datos: ningún otro negocio puede verlos y no se usan para nada más que atender tu WhatsApp.',
            ],
            6 => [
                'q' => '¿Cuándo se cobra y cómo cancelo?',
                'a' => 'Pruebas :days días gratis sin poner tarjeta. Después eliges un plan y puedes cambiarlo o cancelarlo cuando quieras: cancelas y no se te cobra el período siguiente.',
            ],
            7 => [
                'q' => '¿Necesito saber de tecnología?',
                'a' => 'No. Conectas tu WhatsApp escaneando un código QR y cargas tus datos en un panel simple. La mayoría de los negocios queda atendiendo en minutos.',
            ],
        ],
    ],
];
