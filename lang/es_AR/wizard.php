<?php

declare(strict_types=1);

// Voseo-only overrides: anything missing here falls back to lang/es/wizard.php.
return [

    'steps' => [
        1 => [
            'label' => 'Tu cuenta',
        ],
        2 => [
            'label' => 'Rubro y negocio',
            'heading' => 'Contanos de tu negocio',
            'lead' => 'Con el nombre, tu asistente ya sabe presentarse. Miralo a la derecha.',
        ],
        3 => [
            'label' => 'Servicios',
            'heading' => '¿Qué ofrecés?',
            'lead' => 'Cargá al menos uno y mirá cómo tu asistente le contesta a un cliente que lo pregunta.',
        ],
        4 => [
            'label' => 'Productos',
            'heading' => 'Tus productos',
            'lead' => '¿Vendés productos o tenés inventario? Subí tu planilla y tu asistente responde qué hay y qué no. Si no aplica, saltalo.',
        ],
        5 => [
            'label' => 'Conexión',
            'heading' => 'Conectá tu WhatsApp',
            'lead' => 'El último paso: a partir de acá, tu asistente atiende de verdad.',
        ],
    ],

    'fields' => [
        'country_placeholder' => 'Elegí el país',
        'province_placeholder' => 'Elegí la provincia',
        'service_placeholder' => 'Escribí uno y apretá Enter — ej. Ecodoppler',
    ],

    'products' => [
        'drop_title' => 'Arrastrá tu planilla acá',
        'drop_text' => 'Tu lista de precios o inventario, tal cual la tenés. Nosotros la entendemos.',
        'drop_formats' => '.xlsx · .csv · hasta 10 MB',
        'import_ok' => '✓ inventario.xlsx — 1.240 productos leídos. Probalo a la derecha.',
        'skip' => 'Saltar este paso',
    ],

    'whatsapp' => [
        'qr_step_1' => 'Abrí <b>WhatsApp</b> en el teléfono del negocio.',
        'qr_step_2' => 'Andá a <b>Dispositivos vinculados</b>.',
        'qr_step_3' => 'Escaneá este código y listo: tu asistente queda de guardia.',
    ],

    'done' => [
        'heading' => 'Listo. Tu asistente ya responde por vos.',
        'text_connected' => 'Tu número quedó conectado. Lo que salteaste te espera en el panel, sin apuro.',
        'text_pending' => 'Todo lo que cargaste quedó guardado. Conectá tu WhatsApp desde el panel cuando quieras.',
        'cta' => 'Ir a mi panel',
    ],

    'preview' => [
        'title' => 'Así te va a atender',
        'description' => 'Vista previa real: responde con lo que vas cargando.',
        'empty' => 'Cargá el nombre de tu negocio y te muestro cómo se presenta tu asistente.',
    ],

    'phone' => [
        'client' => 'Cliente',
        'assistant' => 'Asistente',
        'assistant_of' => 'Asistente de :business',
        'q_open' => '¡Hola! ¿Atienden ahora?',
        'a_open' => '¡Hola! 👋 Soy el asistente de <b>:business</b>. Sí, estoy para ayudarte las 24 horas. Contame qué necesitás.',
        'q_service' => '¿Hacen :service?',
        'a_service' => '¡Sí! En :business ofrecemos :services. ¿Querés que te agende?',
        'q_product' => '¿Tienen el alternador de un Fiat Palio 1.4?',
        'a_product' => 'Dejame revisar el inventario… ¡Sí! Lo tenemos disponible. ¿Te lo reservo?',
        'connected' => '✓ Conectado a tu WhatsApp. Desde ahora atiendo por vos.',
    ],

];
