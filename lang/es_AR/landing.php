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

    'demo' => [
        'try_label' => 'Probalo: escribile como si fueras un cliente',
        'rubro_label' => 'Elegí un negocio de ejemplo',
        'placeholder' => 'Escribí tu consulta…',
        'limit_reply' => '¡Me encantó atenderte! 🤖 Para seguir, creá tu propio asistente con los datos de tu negocio: responde así de rápido, con tus precios y tus horarios.',
        'share_text' => "Mirá lo que me contestó el asistente de :name 🤖\n\n:chat\n\nProbalo vos también: :url",
        'error_reply' => 'No pude responder ahora. Intentá de nuevo en un momento.',
    ],

    'hero' => [
        'badge' => 'Atiende por vos en WhatsApp',
        'perk_try' => 'Probalo acá mismo, sin registrarte',
        'subtitle' => 'Atendia responde tu WhatsApp, llena tu agenda y muestra tu catálogo a cualquier hora — también a las 3 de la mañana, mientras hacés lo tuyo. Lo conectás en minutos, sin saber de tecnología.',
    ],

    'features' => [
        'subtitle' => 'Sea una clínica o un kiosco, Atendia se adapta a cómo trabajás.',
        'schedule' => [
            'body' => 'Definí días, horarios y capacidad. El asistente ofrece huecos libres y confirma sin que muevas un dedo.',
        ],
        'catalog' => [
            'body' => 'Cargá productos con precio y stock. Si preguntan, responde qué hay, cuánto sale y cuántas quedan — y cuando alguien lo quiere, le avisa a tu equipo.',
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
        'subtitle' => 'Probá gratis :days días el plan :plan, sin tarjeta. Cambiá o cancelá cuando quieras.',
        'trust' => 'Sin tarjeta para empezar · Cancelás cuando quieras',
        'save_yearly' => 'Ahorrás :amount al año',
        'multilang' => 'En cualquier idioma — vos lo leés todo en español',
        'ask_title' => 'Preguntale a AtendIa',
        'ask' => 'Preguntale lo que quieras de tu negocio y te responde al instante con tus datos reales.',
        'calculator' => [
            'subtitle' => 'Mové el control con las consultas de un día normal.',
            'slider_label' => 'Consultas que recibís por día',
            'with_lead' => 'Atendia responde por vos.',
            'with_note' => 'De día y de noche. Vos solo mirás.',
            'verdict_recover' => 'Recuperás',
            'verdict_save' => 'y ahorrás',
        ],
    ],

    'faq' => [
        'items' => [
            [
                'q' => '¿Sirve si vendo productos y no doy turnos?',
                'a' => 'Sí. Cargás tu catálogo con precio y stock, y el asistente responde qué hay, cuánto sale y cuántas quedan. Cuando alguien quiere comprar, te avisa para que lo cierres vos.',
            ],
            [
                'q' => '¿Qué pasa si me escriben en otro idioma?',
                'a' => 'Responde en el idioma en que le escriben — inglés, portugués o el que sea — con los datos de tu negocio. Los avisos para tu equipo llegan siempre en español.',
            ],
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
                'a' => 'Probás :days días gratis sin poner tarjeta. Después elegís un plan y podés cambiarlo o cancelarlo cuando quieras: cancelás y no se te cobra el período siguiente.',
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
        'b7' => '¿Me pasás la dirección?',
    ],

];
