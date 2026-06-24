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
        'login' => 'Ingresar',
        'register' => 'Empezar gratis',
        'open_menu' => 'Abrir menú',
        'close_menu' => 'Cerrar menú',
        'toggle_theme' => 'Cambiar tema',
    ],

    'hero' => [
        'badge' => 'Atiende por ti en WhatsApp',
        'title_1' => 'Tu negocio,',
        'title_2' => 'atendido por IA',
        'subtitle' => 'Conecta tu WhatsApp y deja que el asistente responda, agende turnos y muestre tus productos. Tú lo configuras en minutos desde un panel simple — sin saber de tecnología.',
        'cta_primary' => 'Crear mi asistente',
        'cta_secondary' => 'Ver cómo funciona',
        'perk_trial' => '14 días gratis',
        'perk_card' => 'Sin tarjeta',
        'perk_any' => 'Para cualquier rubro',
    ],

    'logos' => [
        'title' => 'Negocios de todos los rubros ya atienden con Atendia',
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
            'title' => 'Responde 24/7',
            'body' => 'Contesta consultas frecuentes en segundos, en tu tono, incluso cuando duermes o estás con un cliente.',
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
        'eyebrow' => 'Casos reales',
        'title' => 'Un mismo asistente, mil negocios',
        'health' => [
            'who' => 'Profesionales y clínicas',
            'title' => 'Dr. Luis Paz · Cardiología',
            'body' => 'Define estudios, duración y capacidad por día. Atendia agenda turnos, recuerda y reprograma.',
            'tags' => ['Turnos', 'Recordatorios', 'Estudios'],
        ],
        'shop' => [
            'who' => 'Comercios y emprendedoras',
            'title' => 'Pastelería Mía',
            'body' => 'Sube su catálogo con precios. Atendia responde por sabores, toma pedidos y comparte el menú.',
            'tags' => ['Catálogo', 'Pedidos', 'Horarios'],
        ],
    ],

    'pricing' => [
        'eyebrow' => 'Precios',
        'title' => 'Simple y por adelantado',
        'subtitle' => 'Empieza gratis. Cambia o cancela cuando quieras.',
        'featured_badge' => 'Más elegido',
        'per_trial' => '/ 14 días',
        'per_month' => '/ mes',
        'starter' => [
            'name' => 'Inicial',
            'desc' => 'Prueba Atendia con tu propio número.',
            'cta' => 'Empezar gratis',
            'feats' => ['1 número de WhatsApp', 'Respuestas automáticas', 'Agenda o catálogo', 'Panel de control'],
        ],
        'business' => [
            'name' => 'Negocio',
            'desc' => 'Para quien ya vive de atender bien.',
            'cta' => 'Crear mi asistente',
            'feats' => ['Todo lo de Inicial', 'Turnos + catálogo juntos', 'Recordatorios automáticos', 'Reportes y métricas', 'Soporte prioritario'],
        ],
        'pro' => [
            'name' => 'Pro',
            'desc' => 'Equipos y múltiples sucursales.',
            'cta' => 'Hablar con ventas',
            'feats' => ['Todo lo de Negocio', 'Varios números', 'Roles de equipo', 'Integraciones a medida'],
        ],
    ],

    'carousel' => [
        'eyebrow' => 'Clientes',
        'title' => 'Negocios que ya no atienden solos',
        'subtitle' => 'Miles de conversaciones respondidas cada día, en todos los rubros.',
        'testimonials' => [
            ['name' => 'Clínica Vida', 'who' => 'Centro médico', 'quote' => 'Dejamos de perder turnos por no contestar a tiempo. Ahora la agenda se llena sola.'],
            ['name' => 'Pastelería Mía', 'who' => 'Repostería', 'quote' => 'Responde por precios y sabores aunque yo esté horneando. Vendí 30% más en un mes.'],
            ['name' => 'Dr. Luis Paz', 'who' => 'Cardiología', 'quote' => 'Mis pacientes agendan por WhatsApp y reciben recordatorios. Cero ausencias.'],
            ['name' => 'Kiosco Sol', 'who' => 'Comercio', 'quote' => 'Configurarlo me llevó una tarde. Atiende consultas hasta de madrugada.'],
            ['name' => 'Estudio Lex', 'who' => 'Abogados', 'quote' => 'Filtra consultas y agenda reuniones. Llegamos solo a lo que importa.'],
            ['name' => 'AutoFix', 'who' => 'Taller', 'quote' => 'Cotiza service y reserva el turno del auto sin que levante el teléfono.'],
            ['name' => 'Dra. Ríos', 'who' => 'Odontología', 'quote' => 'El asistente habla como hablo yo. Los pacientes ni notan la diferencia.'],
            ['name' => 'Glow Spa', 'who' => 'Estética', 'quote' => 'Reservas, paquetes y promos respondidas al toque. Una genialidad.'],
        ],
    ],

    'closing' => [
        'title' => 'Tu próximo cliente está escribiendo ahora',
        'subtitle' => 'Conecta tu WhatsApp y que Atendia responda por ti en minutos.',
        'cta_primary' => 'Empezar gratis',
        'cta_secondary' => 'Agendar demo',
    ],

    'footer' => [
        'tagline' => 'Publicidad y atención automatizada por WhatsApp para cualquier negocio.',
        'col_product' => 'Producto',
        'col_resources' => 'Recursos',
        'col_company' => 'Empresa',
        'links_product' => ['Funciones', 'Precios', 'Casos', 'Integraciones'],
        'links_resources' => ['Cómo funciona', 'Ayuda', 'Estado', 'Blog'],
        'links_company' => ['Nosotros', 'Contacto', 'Términos', 'Privacidad'],
        'copyright' => 'Hecho para los que atienden.',
        'terms' => 'Términos',
        'privacy' => 'Privacidad',
        'language' => 'Idioma',
    ],

    'phone' => [
        'header' => 'Clínica Vida · Asistente',
        'online' => 'en línea',
        'b1' => 'Hola, ¿tienes turno para un electro esta semana?',
        'b2' => '¡Hola! Sí 😊 Tengo el jueves 10:30 o el viernes 16:00. ¿Cuál te queda mejor?',
        'b3' => 'El jueves 10:30',
        'b4' => 'Listo, te reservé el <b>jueves 10:30</b> con el Dr. Paz. Te llega el recordatorio el día anterior 👍',
    ],

];
