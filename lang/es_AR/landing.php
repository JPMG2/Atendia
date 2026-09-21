<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Landing — variante rioplatense (voseo) · Argentina / Uruguay
|--------------------------------------------------------------------------
|
| SOLO las claves que cambian respecto del neutro. Todo lo que no esté acá
| cae automáticamente a lang/es/landing.php por fallback_locale. No copiar
| el archivo entero: mantener únicamente lo que de verdad difiere.
|
*/

return [

    'hero' => [
        'badge' => 'Atiende por vos en WhatsApp',
        'subtitle' => 'Atendia responde tu WhatsApp, llena tu agenda y muestra tu catálogo a cualquier hora — también a las 3 de la mañana, mientras hacés lo tuyo. Lo conectás en minutos, sin saber de tecnología.',
    ],

    'features' => [
        'subtitle' => 'Sea una clínica o un kiosco, Atendia se adapta a cómo trabajás.',
        'schedule' => [
            'body' => 'Definí días, horarios y capacidad. El asistente ofrece huecos libres y confirma sin que muevas un dedo.',
        ],
        'catalog' => [
            'body' => 'Cargá productos con precio y foto. Si preguntan, responde con la info y el link de compra al instante.',
        ],
        'always' => [
            'body' => 'Contesta en segundos y en el idioma en que le escriban — inglés, portugués o el que sea. Vos lo leés todo en español.',
        ],
        'alerts' => [
            'body' => 'Recibís un resumen de turnos y conversaciones. Intervenís solo cuando hace falta.',
        ],
        'control' => [
            'title' => 'Vos tenés el control',
        ],
    ],

    'how' => [
        'step3' => [
            'title' => 'Responde por vos',
        ],
        'step4' => [
            'title' => 'Vos supervisás',
            'body' => 'Revisás todo desde el panel y tomás el control cuando quieras.',
        ],
    ],

    'pricing' => [
        'subtitle' => 'Probá gratis 14 días el plan Negocio, sin tarjeta. Cambiá o cancelá cuando quieras.',
        'trust' => 'Sin tarjeta para empezar · Cancelás cuando quieras',
        'save_yearly' => 'Ahorrás :amount al año',
        'multilang' => 'En cualquier idioma — vos lo leés todo en español',
    ],

    'faq' => [
        'items' => [
            [
                'q' => '¿Puede decirle algo equivocado a mis clientes?',
                'a' => 'Responde solo con lo que tu negocio cargó: tus servicios, tus precios, tus respuestas. Si algo no está en su conocimiento, lo dice con honestidad y ofrece pasar con tu equipo — nunca inventa.',
            ],
            [
                'q' => '¿Se nota que es un bot?',
                'a' => 'Se presenta como el asistente de tu negocio desde el primer mensaje: nadie es engañado. Escribe con tono cercano, en el idioma del cliente, y vos decidís cuándo deriva a una persona.',
            ],
            [
                'q' => '¿Pierdo el control de mi WhatsApp?',
                'a' => 'No: el número sigue siendo tuyo y ves cada conversación en tu panel. Podés tomar cualquier charla cuando quieras y devolvérsela al asistente con un clic.',
            ],
            [
                'q' => '¿Qué pasa con los datos de mis clientes?',
                'a' => 'Quedan aislados en tu cuenta, con candado a nivel de base de datos: ningún otro negocio puede verlos y no se usan para nada más que atender tu WhatsApp.',
            ],
            [
                'q' => '¿Cuándo se cobra y cómo cancelo?',
                'a' => 'Probás 14 días gratis sin poner tarjeta. Después elegís un plan y podés cambiarlo o cancelarlo cuando quieras: cancelás y no se te cobra el período siguiente.',
            ],
            [
                'q' => '¿Necesito saber de tecnología?',
                'a' => 'No. Conectás tu WhatsApp escaneando un código QR y cargás tus datos en un panel simple. La mayoría de los negocios queda atendiendo en minutos.',
            ],
        ],
        'more_cta' => 'Escribinos por WhatsApp',
    ],

    'closing' => [
        'subtitle' => 'Conectá tu WhatsApp y que Atendia responda por vos en minutos.',
    ],

    'phone' => [
        'b1' => 'Hola, ¿tenés turno para un electro esta semana?',
        'b7' => '¿Me pasás la dirección?',
    ],

];
