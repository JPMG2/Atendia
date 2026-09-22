<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Landing — español neutro (tuteo)
|--------------------------------------------------------------------------
|
| Versión base. Sirve a Venezuela, Colombia, México, Chile y todo el resto
| de Latinoamérica. Las variantes regionales (es_AR, es_VE) solo redefinen
| las claves que cambian; lo que no esté ahí cae acá por fallback_locale.
|
*/

return [

    'nav' => [
        'how' => 'Cómo funciona',
        'features' => 'Funciones',
        'cases' => 'Casos',
        'pricing' => 'Precios',
        'faq' => 'Preguntas',
        'login' => 'Ingresar',
        'register' => 'Crear mi asistente',
        'open_menu' => 'Abrir menú',
        'close_menu' => 'Cerrar menú',
        'toggle_theme' => 'Cambiar tema',
    ],

    'hero' => [
        'badge' => 'Atiende por ti en WhatsApp',
        'title_1' => 'Nunca más pierdas un cliente',
        'title_2' => 'por no contestar',
        'subtitle' => 'Atendia responde tu WhatsApp, llena tu agenda y muestra tu catálogo a cualquier hora — también a las 3 de la mañana, mientras haces lo tuyo. Lo conectas en minutos, sin saber de tecnología.',
        'cta_primary' => 'Crear mi asistente',
        'cta_secondary' => 'Ver cómo funciona',
        'social_proof' => ':count negocios ya atienden con su asistente',
        'perk_trial' => '14 días gratis',
        'perk_card' => 'Sin tarjeta',
    ],

    'demo' => [
        'try_label' => 'Pruébalo: escríbele como si fueras un cliente',
        'rubro_label' => 'Elige un negocio de ejemplo',
        'placeholder' => 'Escribe tu consulta…',
        'send' => 'Enviar',
        'rubros' => [
            'clinica' => [
                'label' => 'Consultorio',
                'name' => 'Clínica Vida',
                'noun' => 'consultorio',
                'chips' => [
                    '¿Tienen turno esta semana?',
                    '¿Cuánto sale una ecografía?',
                    'How much is an ultrasound?',
                ],
            ],
            'peluqueria' => [
                'label' => 'Peluquería',
                'name' => 'Peluquería Lumen',
                'noun' => 'peluquería',
                'chips' => [
                    '¿Cuánto sale el corte?',
                    '¿Hacen color y mechas?',
                    'Do you take walk-ins?',
                ],
                'pool' => [
                    ['side' => 'in', 'text' => '¿Tienen turno el sábado a la tarde?'],
                    ['side' => 'out', 'text' => '¡Sí! Me queda 16:30 con Sofi. ¿Te lo reservo? 😊'],
                    ['side' => 'in', 'text' => 'Do you do balayage?'],
                    ['side' => 'out', 'text' => 'Yes! Balayage from $55.000, hydration treatment included ✨'],
                ],
            ],
            'kiosco' => [
                'label' => 'Kiosco',
                'name' => 'Kiosco El Faro',
                'noun' => 'kiosco',
                'chips' => [
                    '¿Hacen envíos?',
                    '¿Qué promos tienen hoy?',
                    'What time do you open?',
                ],
                'pool' => [
                    ['side' => 'in', 'text' => '¿Están abiertos ahora?'],
                    ['side' => 'out', 'text' => '¡Sí! Hasta las 23 ⏰ ¿Te llevamos algo?'],
                    ['side' => 'in', 'text' => '¿Qué promos hay hoy?'],
                    ['side' => 'out', 'text' => 'Hoy 2x1 en alfajores y la gaseosa de 1,5 L a mitad de precio llevando dos 🎉'],
                ],
            ],
            'dr-juan' => [
                'label' => 'Dr. Juan',
                'name' => 'Dr. Juan Herrera',
                'noun' => 'consultorio',
                'chips' => [
                    '¿Qué días atiende el doctor?',
                    '¿Cuánto sale la consulta?',
                    'How much is a consultation?',
                ],
                'pool' => [
                    ['side' => 'in', 'text' => '¿El doctor atiende mañana?'],
                    ['side' => 'out', 'text' => 'Sí, de 14 a 19. Tengo libre 15:30, ¿te lo reservo?'],
                    ['side' => 'in', 'text' => '¿Cuánto sale la consulta?'],
                    ['side' => 'out', 'text' => 'La consulta particular sale <b>$25.000</b>. Con obra social suele tener cobertura 😊'],
                ],
            ],
            'panaderia' => [
                'label' => 'Panadería',
                'name' => 'Panadería La Espiga',
                'noun' => 'panadería',
                'chips' => [
                    '¿Hacen tortas por encargo?',
                    '¿Tienen productos sin TACC?',
                    'Do you deliver?',
                ],
                'pool' => [
                    ['side' => 'in', 'text' => '¿Hay medialunas para las 9?'],
                    ['side' => 'out', 'text' => '¡Recién horneadas! 🥐 ¿Docena o media docena?'],
                    ['side' => 'in', 'text' => 'Do you make custom cakes?'],
                    ['side' => 'out', 'text' => 'Yes! From $28.000 per kilo — just order 48h ahead 🎂'],
                ],
            ],
            'restaurante' => [
                'label' => 'Restaurante',
                'name' => 'Restaurante La Nona',
                'noun' => 'restaurante',
                'chips' => [
                    '¿Puedo reservar para hoy?',
                    '¿Tienen menú del día?',
                    'Do you have a table tonight?',
                ],
                'pool' => [
                    ['side' => 'in', 'text' => '¿Mesa para 4 hoy a las 21?'],
                    ['side' => 'out', 'text' => '¡Confirmada! Mesa para 4 a las 21, a tu nombre 🍷'],
                    ['side' => 'in', 'text' => '¿Tienen menú del día?'],
                    ['side' => 'out', 'text' => 'Sí: entrada, principal y postre por <b>$22.000</b>, de martes a viernes al mediodía.'],
                ],
            ],
            'ferreteria' => [
                'label' => 'Ferretería',
                'name' => 'Ferretería El Tornillo',
                'noun' => 'ferretería',
                'chips' => [
                    '¿Hacen copias de llaves?',
                    '¿Llevan pedidos a obra?',
                    'What time do you open?',
                ],
                'pool' => [
                    ['side' => 'in', 'text' => '¿Tienen cinta de teflón y llave del 14?'],
                    ['side' => 'out', 'text' => 'Sí, las dos en stock. ¿Te las aparto en el mostrador?'],
                    ['side' => 'in', 'text' => '¿Llevan pedidos a obra?'],
                    ['side' => 'out', 'text' => 'Sí: gratis desde $50.000 en el barrio. Antes de las 14 sale el mismo día 🚚'],
                ],
            ],
            'veterinaria' => [
                'label' => 'Veterinaria',
                'name' => 'Veterinaria Patitas',
                'noun' => 'veterinaria',
                'chips' => [
                    '¿Atienden urgencias?',
                    '¿Cuánto sale la consulta?',
                    'Do you do grooming?',
                ],
                'pool' => [
                    ['side' => 'in', 'text' => 'Mi gato no quiere comer 😿'],
                    ['side' => 'out', 'text' => 'Pobre 💚 Hoy a las 18:00 hay lugar con la doctora, ¿te lo agendo?'],
                    ['side' => 'in', 'text' => 'Do you do grooming?'],
                    ['side' => 'out', 'text' => 'Yes! Bath & cut from $18.000, with appointment 🐾'],
                ],
            ],
        ],
        'header' => ':name · Asistente',
        'greeting' => '¡Hola! 🤖 Soy el asistente de :name. ¿En qué te puedo ayudar?',
        'left' => '{1}Te queda 1 pregunta de prueba|[2,*]Te quedan :count preguntas de prueba',
        'share' => 'Compartir esta charla',
        'share_text' => "Mira lo que me contestó el asistente de :name 🤖\n\n:chat\n\nPruébalo tú también: :url",
        'limit_reply' => '¡Me encantó atenderte! 🤖 Para seguir, crea tu propio asistente con los datos de tu negocio: responde así de rápido, con tus precios y tus horarios.',
        'error_reply' => 'No pude responder ahora. Intenta de nuevo en un momento.',
        'cta_more' => 'Crear mi asistente gratis',
        'cta_rubro' => 'Crear el asistente de mi :rubro',
    ],

    'logos' => [
        'title' => 'Negocios de todos los rubros ya atienden con Atendia',
    ],

    'tally' => [
        'line' => ':count conversación respondida esta semana|:count conversaciones respondidas esta semana',
    ],

    'features' => [
        'eyebrow' => 'Funciones',
        'title' => 'Todo lo que tu negocio necesita para atender mejor',
        'subtitle' => 'Sea una clínica o un kiosco, Atendia se adapta a cómo trabajas.',
        'schedule' => [
            'title' => 'Agenda turnos sola',
            'body' => 'Define días, horarios y capacidad. El asistente ofrece huecos libres y confirma sin que muevas un dedo.',
        ],
        'catalog' => [
            'title' => 'Muestra tu catálogo',
            'body' => 'Carga productos con precio y foto. Si preguntan, responde con la info y el link de compra al instante.',
        ],
        'always' => [
            'title' => 'Responde 24/7 en cualquier idioma',
            'body' => 'Contesta en segundos y en el idioma en que le escriban — inglés, portugués o el que sea. Tú lo lees todo en español.',
        ],
        'alerts' => [
            'title' => 'Te avisa lo importante',
            'body' => 'Recibes un resumen de turnos y conversaciones. Intervienes solo cuando hace falta.',
        ],
        'control' => [
            'title' => 'Tú tienes el control',
            'body' => 'Todo se configura desde un panel claro: precios, horarios, mensajes y respuestas automáticas.',
        ],
        'brand' => [
            'title' => 'Tu número, tu marca',
            'body' => 'Funciona sobre tu propio WhatsApp. Tus clientes hablan con tu negocio, no con un bot genérico.',
        ],
        'vignettes' => [
            'panel_tab' => 'Inicio',
            'stat_conversations' => 'Conversaciones',
            'stat_resolution' => 'Tasa de resolución',
            'thread_1_name' => 'Mariana',
            'thread_1_text' => '¿Tienen turno mañana a la tarde?',
            'thread_1_badge' => 'Resuelta',
            'thread_2_name' => 'Diego',
            'thread_2_text' => 'Do you ship to Miami?',
            'thread_2_badge' => 'En inglés',
            'always_pills' => ['24/7', 'Español', 'English', 'Português'],
            'alert_text' => 'Resumen del día: 8 turnos confirmados, 2 charlas esperando tu respuesta',
            'schedule_text' => 'Sábado 16:30 · Corte con Sofi',
            'schedule_badge' => 'Confirmado',
            'catalog_1_name' => 'Corte + lavado',
            'catalog_1_price' => '$18.000',
            'catalog_2_name' => 'Mechas',
            'catalog_2_price' => 'desde $55.000',
            'brand_name' => 'Peluquería Lumen',
            'brand_badge' => 'Tu número',
            'brand_greeting' => 'Hola, soy el asistente de Peluquería Lumen',
        ],
    ],

    'how' => [
        'eyebrow' => 'Cómo funciona',
        'title' => 'De la pregunta a la respuesta, automático',
        'step1' => [
            'title' => 'El cliente escribe',
            'body' => 'Manda un mensaje a tu WhatsApp como siempre.',
        ],
        'step2' => [
            'title' => 'Atendia lo procesa',
            'body' => 'El flujo automático entiende la consulta y busca la respuesta en tu configuración.',
        ],
        'step3' => [
            'title' => 'Responde por ti',
            'body' => 'Contesta, agenda o cotiza al instante, con tu tono y tus datos.',
        ],
        'step4' => [
            'title' => 'Tú supervisas',
            'body' => 'Revisas todo desde el panel y tomas el control cuando quieras.',
        ],
    ],

    'cases' => [
        'eyebrow' => 'Casos de uso',
        'title' => 'Un mismo asistente, mil negocios',
        'health' => [
            'who' => 'Profesionales y clínicas',
            'title' => 'Consultorio de cardiología',
            'body' => 'Define estudios, duración y capacidad por día. Atendia agenda turnos, recuerda y reprograma.',
            'tags' => ['Turnos', 'Recordatorios', 'Estudios'],
        ],
        'shop' => [
            'who' => 'Comercios y emprendedoras',
            'title' => 'Pastelería artesanal',
            'body' => 'Sube su catálogo con precios. Atendia responde por sabores, toma pedidos y comparte el menú.',
            'tags' => ['Catálogo', 'Pedidos', 'Horarios'],
        ],
    ],

    'pricing' => [
        'eyebrow' => 'Precios',
        'title' => 'Simple y por adelantado',
        'subtitle' => 'Prueba gratis 14 días el plan Negocio, sin tarjeta. Cambia o cancela cuando quieras.',
        'featured_badge' => 'Más elegido',
        'per_trial' => '/ 14 días',
        'per_month' => '/ mes',
        'per_month_yearly' => '/ mes, facturado anual',
        'billing_monthly' => 'Mensual',
        'billing_yearly' => 'Anual',
        'billing_yearly_badge' => '2 meses gratis',
        'save_yearly' => 'Ahorras :amount al año',
        'trust' => 'Sin tarjeta para empezar · Cancelas cuando quieras',
        'currency_note' => 'Precios en dólares (USD)',
        'local_reference' => 'En tu moneda, el plan :plan ronda los :amount al mes — valor de referencia.',
        'calculator' => [
            'title' => '¿Cuánto te cuesta contestar a mano?',
            'subtitle' => 'Mueve el control y míralo con tus números.',
            'slider_label' => 'Consultas que recibes por día',
            'out_conversations' => 'conversaciones al mes',
            'out_hours' => 'horas tuyas contestando',
            'hours_note' => 'Atendia te las devuelve — también a las 3 a.m.',
            'plan_hint' => 'Con ese volumen te alcanza el plan',
            'assumption' => 'Estimación con :minutes min por consulta.',
        ],
        'multilang' => 'En cualquier idioma — tú lo lees todo en español',
        'emprende' => [
            'name' => 'Emprende',
            'desc' => 'Tu asistente atendiendo desde el primer día.',
            'cta' => 'Crear mi asistente',
            'feats' => ['1 número de WhatsApp', '300 conversaciones con IA al mes', 'Agenda o catálogo', 'Estadísticas del negocio', 'Usuarios del panel sin límite'],
        ],
        'negocio' => [
            'name' => 'Negocio',
            'desc' => 'Para el negocio que conversa todos los días.',
            'cta' => 'Crear mi asistente',
            'includes' => 'Todo lo de Emprende, más:',
            'feats' => ['1.000 conversaciones con IA al mes', '2 números de WhatsApp', 'Notas de voz de tus clientes', 'Resumen diario y reportes a pedido', 'Tu equipo entra cuando hace falta'],
        ],
        'premium' => [
            'name' => 'Premium',
            'desc' => 'Máximo volumen y tu IA a medida.',
            'cta' => 'Hablar con ventas',
            'whatsapp_text' => 'Hola, quiero saber más del plan Premium de Atendia.',
            'includes' => 'Todo lo de Negocio, más:',
            'feats' => ['3.000 conversaciones con IA al mes', 'Hasta 5 números de WhatsApp', 'Tu asistente a tu medida', 'Analítica avanzada', 'Configuración asistida incluida'],
        ],
    ],

    'carousel' => [
        'eyebrow' => 'Clientes',
        'title' => 'Negocios que ya no atienden solos',
        'subtitle' => 'Opiniones reales de negocios que atienden con Atendia.',
    ],

    'faq' => [
        'eyebrow' => 'Preguntas frecuentes',
        'title' => 'Lo que todos preguntan antes de conectar',
        'items' => [
            [
                'q' => '¿Puede decirle algo equivocado a mis clientes?',
                'a' => 'Responde solo con lo que tu negocio cargó: tus servicios, tus precios, tus respuestas. Si algo no está en su conocimiento, lo dice con honestidad y ofrece pasar con tu equipo — nunca inventa.',
            ],
            [
                'q' => '¿Se nota que es un bot?',
                'a' => 'Se presenta como el asistente de tu negocio desde el primer mensaje: nadie es engañado. Escribe con tono cercano, en el idioma del cliente, y tú decides cuándo deriva a una persona.',
            ],
            [
                'q' => '¿Pierdo el control de mi WhatsApp?',
                'a' => 'No: el número sigue siendo tuyo y ves cada conversación en tu panel. Puedes tomar cualquier charla cuando quieras y devolverla al asistente con un clic.',
            ],
            [
                'q' => '¿Qué pasa con los datos de mis clientes?',
                'a' => 'Quedan aislados en tu cuenta, con candado a nivel de base de datos: ningún otro negocio puede verlos y no se usan para nada más que atender tu WhatsApp.',
            ],
            [
                'q' => '¿Cuándo se cobra y cómo cancelo?',
                'a' => 'Pruebas 14 días gratis sin poner tarjeta. Después eliges un plan y puedes cambiarlo o cancelarlo cuando quieras: cancelas y no se te cobra el período siguiente.',
            ],
            [
                'q' => '¿Necesito saber de tecnología?',
                'a' => 'No. Conectas tu WhatsApp escaneando un código QR y cargas tus datos en un panel simple. La mayoría de los negocios queda atendiendo en minutos.',
            ],
        ],
        'more' => '¿Otra duda?',
        'more_cta' => 'Escríbenos por WhatsApp',
        'whatsapp_text' => 'Hola, tengo una pregunta sobre Atendia.',
    ],

    'closing' => [
        'title' => 'Tu próximo cliente está escribiendo ahora',
        'subtitle' => 'Conecta tu WhatsApp y que Atendia responda por ti en minutos.',
        'cta_primary' => 'Crear mi asistente',
        'cta_secondary' => 'Ver cómo funciona',
    ],

    'footer' => [
        'tagline' => 'Publicidad y atención automatizada por WhatsApp para cualquier negocio.',
        'col_product' => 'Producto',
        'link_features' => 'Funciones',
        'link_how' => 'Cómo funciona',
        'link_cases' => 'Casos',
        'link_pricing' => 'Precios',
        'link_faq' => 'Preguntas',
        'copyright' => 'Hecho para los que atienden.',
        'language' => 'Idioma',
    ],

    'phone' => [
        'header' => 'Clínica Vida · Asistente',
        'online' => 'en línea',
        'b1' => 'Hola, ¿tienes turno para un electro esta semana?',
        'b2' => '¡Hola! Sí 😊 Tengo el jueves 10:30 o el viernes 16:00. ¿Cuál te queda mejor?',
        'b3' => 'El jueves 10:30',
        'b4' => 'Listo, te reservé el <b>jueves 10:30</b> con el Dr. Paz. Te llega el recordatorio el día anterior 👍',
        'b5' => '¿Puedo pagar con tarjeta?',
        'b6' => '¡Claro! Aceptamos tarjeta, transferencia o efectivo 😊',
        'b7' => '¿Me pasas la dirección?',
        'b8' => 'Av. Libertad 742, a una cuadra de la plaza. ¡Te esperamos! 📍',
        // En inglés a propósito: la demo muestra que el asistente responde
        // en el idioma del cliente. Igual en todas las variantes regionales.
        'b9' => 'Hi! Can I book a check-up for tomorrow?',
        'b10' => 'Of course! 😊 Tomorrow at 11:00 is free — booked! See you then 👍',
    ],

];
