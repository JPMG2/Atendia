<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Panel del cliente — Inicio
|--------------------------------------------------------------------------
|
| Base neutra (tuteo). Los verbos que cambian con el voseo se sobrescriben
| en lang/es_AR/client.php; lo que no está allá cae a este archivo.
*/

return [
    'home' => [
        'title' => 'Inicio',
        'greeting' => 'Hola, :name',
        'sub_new' => 'Tu asistente está casi listo. Esto es lo que falta.',
        'sub_active' => 'Así viene tu negocio hoy.',
        'mock_label' => 'Vista de maqueta',
        'state_new' => 'Recién llegado',
        'state_active' => 'Activo',
    ],

    'setup' => [
        'title' => 'Tu asistente está listo al :percent%',
        'sub' => 'Completa estos pasos y tu negocio responde solo, a toda hora.',
        'progress' => ':done de :total',
        'steps' => [
            'account' => [
                'label' => 'Creaste tu cuenta',
                'hint' => 'Este ya está. Buen comienzo.',
            ],
            'business' => [
                'label' => 'Completa los datos de tu negocio',
                'hint' => 'Logo, dirección y horarios: tu asistente se presenta mejor.',
                'cta' => 'Completar',
            ],
            'catalog' => [
                'label' => 'Carga tus servicios o productos',
                'hint' => 'Es lo que tu asistente sabe responder.',
                'cta' => 'Cargar',
            ],
            'try' => [
                'label' => 'Prueba tu asistente',
                'hint' => 'Escríbele como si fueras un cliente y míralo responder.',
                'cta' => 'Probar',
            ],
            'whatsapp' => [
                'label' => 'Conecta tu WhatsApp',
                'hint' => 'Desde ese momento responde por ti las 24 horas.',
                'cta' => 'Conectar',
            ],
        ],
    ],

    'previews' => [
        'conversations_title' => 'Conversaciones',
        'conversations_text' => 'Cada charla que tu asistente atienda queda aquí, con lo que respondió.',
        'metrics_title' => 'Tu día en números',
        'metrics_text' => 'Cuántos clientes escribieron y qué preguntaron, en vivo.',
    ],

    'kpis' => [
        'conversations' => 'Conversaciones hoy',
        'handled' => 'Resueltas por la IA',
        'handoffs' => 'Derivadas a ti',
        'response' => 'Respuesta promedio',
    ],

    'recent' => [
        'title' => 'Últimas conversaciones',
        'view_all' => 'Ver todas',
        'samples' => [
            ['name' => 'Mariana', 'text' => '¿Tienen turno para mañana a la tarde?', 'time' => '10:24'],
            ['name' => 'Jorge', 'text' => '¿Cuánto sale el ecodoppler?', 'time' => '09:51'],
            ['name' => 'Valeria', 'text' => '¿Hacen envíos a Palermo?', 'time' => '09:12'],
        ],
    ],

    'business' => [
        'title' => 'Mi negocio',
        'back' => 'Volver al inicio',
        'sub' => 'Todo lo que completes aquí ayuda a tu asistente a presentarse y responder mejor. Nada es obligatorio.',
        'optional' => 'opcional',
        'recommended' => 'Recomendado',

        'identity' => [
            'title' => 'Identidad',
            'sub' => 'Cómo te ve un cliente cuando tu asistente lo atiende.',
            'logo' => 'Logo',
            'logo_hint' => 'PNG o JPG. Aparece en tu perfil y junto a las respuestas del asistente.',
            'name' => 'Nombre del negocio',
            'description' => 'Descripción',
            'description_hint' => 'Tu asistente la usa para presentarse. Cuenta qué haces y qué te hace distinto.',
        ],

        'location' => [
            'title' => 'Ubicación',
            'sub' => 'Si atiendes en un local, tu asistente puede pasar la dirección cuando se la pidan.',
            'question' => '¿Atiendes clientes en un local?',
            'yes' => 'Sí',
            'no' => 'No',
            'address' => 'Dirección',
            'address_placeholder' => 'Av. Bolívar Norte, local 4',
            'city' => 'Ciudad',
            'city_placeholder' => 'Valencia',
            'country' => 'País',
            'province' => 'Provincia / estado',
        ],

        'hours' => [
            'title' => 'Horarios de atención',
            'sub' => 'Tu asistente responde a toda hora; con esto además sabe decir cuándo estás abierto.',
            'add_shift' => 'Agregar turno',
            'apply_weekdays' => 'Aplicar de lunes a viernes',
            'closed' => 'Cerrado',
            'opens' => 'Abre',
            'closes' => 'Cierra',
            'time_placeholder' => '09:00',
            'remove_shift' => 'Quitar este turno',
        ],

        'contact' => [
            'title' => 'Contacto',
            'sub' => 'Otras vías además del WhatsApp, para tu perfil público.',
            'email' => 'Correo del negocio',
            'web' => 'Sitio web',
            'web_placeholder' => 'https://tunegocio.com',
        ],

        'social' => [
            'title' => 'Redes sociales',
            'sub' => 'Los perfiles que tu asistente puede compartir cuando se los pidan.',
            'network' => 'Red',
            'network_placeholder' => 'Elige una red',
            'url' => 'Enlace o usuario',
            'url_placeholder' => 'https://facebook.com/tunegocio',
            'add' => 'Agregar red',
            'remove' => 'Quitar esta red',
        ],

        'billing' => [
            'title' => 'Moneda y facturación',
            'sub' => 'Cómo se muestran tus precios y a nombre de quién sale tu factura de AtendIa.',
            'currency' => 'Moneda de tus precios',
            'reference' => 'Moneda de referencia',
            'reference_hint' => 'La referencia se muestra al lado del precio: Bs 4.200 · Ref $2',
            'tax_condition' => 'Condición fiscal',
            'tax_condition_placeholder' => 'Elegir…',
            'tax_id' => 'Número fiscal',
            'tax_id_placeholder' => 'RIF / CUIT',
            'natural_hint' => '¿No tienes datos fiscales? Sin problema: tu factura sale a tu nombre, como persona natural.',
        ],

        'try' => [
            'button' => 'Pruébalo ahora',
            'title' => 'Así responde hoy',
            'online' => 'en línea',
            'close' => 'Cerrar',
            'q1' => '¿Hacen arreglos de vestidos?',
            'a1' => '¡Sí! Hacemos arreglos y confección a medida. El dobladillo simple te lo entrego en el día.',
            'q2' => '¿Hasta qué hora están hoy?',
            'a2' => 'Hoy atendemos de 09:00 a 13:00 y de 16:00 a 20:00. ¡Te esperamos!',
            'hint' => 'Responde con lo que cargaste hasta ahora.',
        ],

        'unsaved' => [
            'title' => '¿Salir sin guardar?',
            'message' => 'Los cambios de esta sección se van a perder.',
            'accept' => 'Salir sin guardar',
        ],

        'meter' => [
            'title' => 'Tu perfil, al :percent%',
            'count' => ':done de :total',
            'complete_title' => 'Perfil completo',
            'complete_sub' => 'Tu asistente ya tiene todo para presentarse como un grande.',
            'personal_data' => 'Datos de contacto',
            'tax_details' => 'Moneda y facturación',
            'schedule' => 'Horarios',
            'social_media' => 'Redes sociales',
        ],

        'preview' => [
            'title' => 'Así se presenta',
            'sub' => 'Con lo que cargaste, tu asistente arranca así:',
            'message' => '¡Hola! 👋 Soy el asistente de :name. :description ¿En qué te ayudo?',
            'caption' => 'La descripción y el logo salen aquí. Probá: editá y miralo cambiar.',
            'thanks' => [
                'identidad' => 'Ahora sé presentarte mejor.',
                'ubicacion' => 'Ahora sé decirles dónde encontrarte.',
                'horarios' => 'Ahora sé decirles cuándo estás abierto.',
                'contacto' => 'Ahora sé por dónde más contactarte.',
                'redes' => 'Ahora sé compartir tus redes.',
                'facturacion' => 'Ahora sé mostrar tus precios como corresponde.',
            ],
        ],

        'mock' => [
            'name' => 'Costuras Mary',
            'description' => 'Arreglos y confección a medida en Valencia. Dobladillos en el día y retiro en el local.',
            'email' => 'hola@costurasmary.com',
            'country' => 'Venezuela',
            'province' => 'Carabobo',
            'currency' => 'Bolívar (Bs)',
            'reference' => 'Dólar (USD)',
        ],

        'actions' => [
            'save' => 'Guardar',
        ],
    ],

    'services' => [
        'title' => 'Tus servicios',
        'sub' => 'Lo que ofreces, tal como tu asistente lo cuenta cuando se lo preguntan.',
        'state_label' => 'Estado de la maqueta',
        'state_empty' => 'Sin cargar',
        'state_loaded' => 'Con servicios',
        'count' => '{1} :count servicio|[2,*] :count servicios',
        'add' => 'Agregar servicio',
        'add_short' => 'Agregar',
        'add_placeholder' => 'Escribe un servicio, por ejemplo "Corte de dama"',
        'suggestions' => 'Sugerencias de tu rubro:',
        'empty_title' => 'Todavía no cargaste servicios',
        'empty_body' => 'Cuando cargues el primero, tu asistente va a responder "¿hacen tal cosa?" con tu propia lista.',
        'active' => 'Activo',
        'paused' => 'Pausado',
    ],

    'products' => [
        'title' => 'Tus productos',
        'sub' => 'Lo que vendes; tu asistente responde "¿tienen tal cosa?" con esta lista.',
        'state_label' => 'Estado de la maqueta',
        'state_empty' => 'Sin cargar',
        'state_loaded' => 'Con productos',
        'count' => '{1} :count producto|[2,*] :count productos',
        'add' => 'Agregar producto',
        'add_short' => 'Agregar',
        'add_placeholder' => 'Escribe un producto, por ejemplo "Bujía NGK"',
        'remove' => 'Quitar :name',
        'empty_title' => 'Todavía no cargaste productos',
        'empty_body' => 'Sube tu Excel y tu asistente aprende el catálogo completo en minutos.',
        'empty_or' => 'O escríbelos de a uno cuando quieras.',
        'import_title' => 'Importar desde Excel',
        'import_body' => 'Sube la planilla que ya usas: leemos las columnas y tu asistente aprende cada fila.',
        'import_cta' => 'Subir Excel',
        'import_last' => 'Última importación: :file · :rows filas procesadas',
    ],
];
