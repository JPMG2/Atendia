<?php

declare(strict_types=1);

// Copy for the client onboarding wizard. Neutral base (tuteo); es_AR overrides
// only the strings whose verbs change under voseo.
return [

    'title' => 'Alta de cliente',
    'progress' => 'Paso :current de :total',
    'save_exit' => 'Guardar y salir',
    'continue' => 'Continuar',
    'optional' => 'Opcional',

    'steps' => [
        1 => [
            'label' => 'Tu cuenta',
        ],
        2 => [
            'label' => 'Rubro y negocio',
            'heading' => 'Cuéntanos de tu negocio',
            'lead' => 'Con el nombre, tu asistente ya sabe presentarse. Míralo a la derecha.',
        ],
        3 => [
            'label' => 'Servicios',
            'heading' => '¿Qué ofreces?',
            'lead' => 'Carga al menos uno y mira cómo tu asistente le contesta a un cliente que lo pregunta.',
        ],
        4 => [
            'label' => 'Productos',
            'heading' => 'Tus productos',
            'lead' => '¿Vendes productos o tienes inventario? Sube tu planilla y tu asistente responde qué hay y qué no. Si no aplica, sáltalo.',
        ],
        5 => [
            'label' => 'Conexión',
            'heading' => 'Conecta tu WhatsApp',
            'lead' => 'El último paso: a partir de aquí, tu asistente atiende de verdad.',
        ],
    ],

    'fields' => [
        'business_name' => 'Nombre del negocio',
        'business_name_placeholder' => 'Clínica Vida',
        'business_name_hint' => 'Como lo conocen tus clientes: es el nombre con el que el asistente se presenta.',

        'country' => 'País',
        'country_placeholder' => 'Elige el país',

        'province' => 'Provincia',
        'province_placeholder' => 'Elige la provincia',
        'province_hint' => 'Con esto tu asistente sabe tu zona horaria: los mensajes salen en tu hora local.',

        'sector' => '¿A qué se dedica?',
        'sector_hint' => 'Elegir un rubro le da a tu asistente sugerencias hechas para tu oficio. Se puede cambiar después.',

        'service' => 'Tus servicios',
        'service_placeholder' => 'Escribe uno y aprieta Enter — ej. Ecodoppler',

        'whatsapp_number' => 'WhatsApp del negocio',
        'whatsapp_number_placeholder' => '+54 9 341 512 4408',
        'whatsapp_number_hint' => 'El número al que te escriben tus clientes. No mandamos nada sin avisarte.',

        'fallback_whatsapp_number' => 'WhatsApp para derivar',
        'fallback_whatsapp_number_placeholder' => '+54 9 341 555 0199',
        'fallback_whatsapp_number_hint' => 'Tu número o el de alguien del equipo: ahí te pasamos los mensajes que la IA no pueda responder.',

        'business_email' => 'Correo del negocio',
        'business_email_placeholder' => 'hola@tunegocio.com',
        'business_email_hint' => 'Aquí te damos la bienvenida y te avisamos lo importante. Nada de spam.',
    ],

    /*
     * The sector chips and the service suggestions are NOT here on purpose:
     * they are catalog data (business_sectors → activities → service_types),
     * curated demand-first in the seeders from the 2026-09 research.
     */
    'services' => [
        'suggest' => 'Sugerencias para tu rubro:',
        'remove' => 'Quitar',
        'skip' => 'Saltar por ahora',
    ],

    'products' => [
        'drop_title' => 'Arrastra tu planilla aquí',
        'drop_text' => 'Tu lista de precios o inventario, tal cual la tienes. Nosotros la entendemos.',
        'drop_formats' => '.xlsx · .csv · hasta 10 MB',
        'import_ok' => '✓ inventario.xlsx — 1.240 productos leídos. Pruébalo a la derecha.',
        'skip' => 'Saltar este paso',
    ],

    'whatsapp' => [
        'qr_step_1' => 'Abre <b>WhatsApp</b> en el teléfono del negocio.',
        'qr_step_2' => 'Ve a <b>Dispositivos vinculados</b>.',
        'qr_step_3' => 'Escanea este código y listo: tu asistente queda de guardia.',
        'later' => 'Conectar más tarde',
        'scanned' => 'Ya escaneé el código',
    ],

    'done' => [
        'heading' => 'Listo. Tu asistente ya responde por ti.',
        'text_connected' => 'Tu número quedó conectado. Lo que saltaste te espera en el panel, sin apuro.',
        'text_pending' => 'Todo lo que cargaste quedó guardado. Conecta tu WhatsApp desde el panel cuando quieras.',
        'cta' => 'Ir a mi panel',
    ],

    'preview' => [
        'title' => 'Así te va a atender',
        'description' => 'Vista previa real: responde con lo que vas cargando.',
        'empty' => 'Carga el nombre de tu negocio y te muestro cómo se presenta tu asistente.',
    ],

    'phone' => [
        'client' => 'Cliente',
        'assistant' => 'Asistente',
        'assistant_of' => 'Asistente de :business',
        'q_open' => '¡Hola! ¿Atienden ahora?',
        'a_open' => '¡Hola! 👋 Soy el asistente de <b>:business</b>. Sí, estoy para ayudarte las 24 horas. Cuéntame qué necesitas.',
        'q_service' => '¿Hacen :service?',
        'a_service' => '¡Sí! En :business ofrecemos :services. ¿Quieres que te agende?',
        'q_product' => '¿Tienen el alternador de un Fiat Palio 1.4?',
        'a_product' => 'Déjame revisar el inventario… ¡Sí! Lo tenemos disponible. ¿Te lo reservo?',
        'connected' => '✓ Conectado a tu WhatsApp. Desde ahora atiendo por ti.',
    ],

    'tip_tag' => 'Tip de experto',
    'tips' => [
        1 => 'Responder en menos de <b>5 minutos</b> multiplica por <b>21</b> las chances de cerrar la venta. Tu asistente va a responder en segundos.',
        2 => 'Un asistente que se presenta con <b>tu nombre</b> genera confianza al instante: nadie confía en un robot anónimo.',
        3 => 'Con tus servicios cargados, tu asistente <b>nunca más dice «no sé»</b>: cada consulta curiosa se vuelve un turno posible.',
        4 => 'Con tu inventario adentro, cada «¿tienen…?» se contesta solo. <b>Incluso a las 3 de la mañana.</b>',
        5 => 'El <b>90%</b> de los mensajes de WhatsApp se leen en menos de 3 minutos. Del otro lado, ahora siempre hay alguien.',
        6 => 'Los negocios que atienden con IA recuperan en promedio <b>horas por día</b>. Las tuyas empiezan ahora.',
    ],

    'todo' => [
        'title' => 'Lo que falta',
        1 => 'Tu cuenta',
        2 => 'Rubro y negocio',
        3 => 'Al menos un servicio',
        4 => 'Productos',
        5 => 'WhatsApp conectado',
    ],

];
