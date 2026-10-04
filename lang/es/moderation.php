<?php

declare(strict_types=1);

// Moderación de contenido: lo que lee el negocio suspendido y lo que ve el admin.
return [
    'upload' => [
        'rejected' => 'No podemos usar esta imagen: parece tener contenido para adultos. Elige otra.',
        'severe' => 'No podemos usar esta imagen: incumple nuestras políticas de uso. Tu cuenta quedó en revisión.',
        'unavailable' => 'No pudimos revisar la imagen. Inténtalo de nuevo en unos minutos.',
    ],

    'banner' => [
        'title' => 'Tu asistente está suspendido.',
        'body' => 'Detectamos contenido que incumple nuestras políticas de uso y tu asistente no responde mientras lo revisamos. No se borró nada.',
    ],

    'mail' => [
        'eyebrow' => 'Políticas de uso',
        'subject' => 'Suspendimos tu asistente de :brand',
        'preheader' => 'Detectamos contenido que incumple nuestras políticas de uso.',
        'title' => 'Tu asistente está suspendido',
        'intro' => 'Hola, detectamos contenido que incumple nuestras políticas de uso en lo que se cargó en la cuenta de :name.',
        'body' => 'Tu asistente dejó de responder mientras lo revisamos. No se borró nada. Si crees que es un error, apela desde tu panel y lo revisamos.',
        'cta' => 'Ir a mi panel',
        'closing' => 'Estamos para ayudarte.',
        'reason' => 'Recibiste este correo porque eres parte del equipo de este negocio en :brand.',
    ],

    'whatsapp' => '⚠️ *:brand*: suspendimos el asistente de *:name* por contenido que incumple nuestras políticas de uso. No se borró nada. Si crees que es un error, apela desde tu panel.',

    'alert' => [
        'subject' => 'Moderación: :business · :severity',
        'preheader' => 'El filtro de contenido detectó algo en un negocio.',
        'eyebrow' => 'Moderación',
        'title' => 'Contenido detectado en :business',
        'intro' => 'Origen: :source · Categoría: :category · Puntaje: :score',
        'cta' => 'Revisar en el panel',
        'closing' => 'El contenido no se guardó: solo su huella.',
        'reason' => 'Recibiste este correo porque eres la administradora de :brand.',
    ],

    'appeal' => [
        'open' => '¿Crees que es un error? Apelar',
        'field' => 'Cuéntanos por qué',
        'placeholder' => 'Por ejemplo: vendemos trajes de baño y la foto es de nuestro catálogo.',
        'send' => 'Enviar apelación',
        'cancel' => 'Cancelar',
        'sent' => 'Recibimos tu apelación. Te respondemos por correo.',
        'already' => 'Ya recibimos tu apelación: la estamos revisando.',
        'waiting' => 'Recibimos tu apelación el :date. Te respondemos por correo.',
        'mail_subject' => 'Apelación: :business',
        'mail_preheader' => 'Un negocio suspendido pide una segunda revisión.',
        'mail_title' => ':business apeló su suspensión',
        'mail_intro' => 'Esto es lo que escribió:',
        'admin_label' => 'Apelación',
    ],

    'severity' => [
        'rejected' => 'Rechazado, sin suspender',
        'severe' => 'Suspendió el negocio',
    ],

    'sources' => [
        'logo' => 'Logo',
        'avatar' => 'Foto de perfil',
        'receipt' => 'Comprobante de pago',
        'profile' => 'Perfil del negocio',
        'services' => 'Servicios',
        'products' => 'Productos',
        'faq' => 'Respuestas enseñadas',
        'catalog_photo' => 'Foto del catálogo',
        'import' => 'Lista importada',
    ],

    'admin' => [
        'title' => 'Moderación',
        'sub' => 'Lo que el filtro de contenido detectó en archivos y textos de los negocios. El contenido no se guarda: solo su huella.',
        'queue_title' => 'Por revisar',
        'queue_empty' => 'Nada por revisar.',
        'recent_title' => 'Revisados',
        'recent_empty' => 'Todavía no hay revisados.',
        'business' => 'Negocio',
        'source' => 'Origen',
        'category' => 'Categoría',
        'score' => 'Puntaje',
        'result' => 'Resultado',
        'date' => 'Fecha',
        'suspended' => 'Suspendido',
        'review' => 'Marcar revisado',
        'lift' => 'Levantar suspensión',
        'lift_title' => '¿Levantar la suspensión de :business?',
        'lift_message' => 'Su asistente vuelve a responder y sus alertas se cierran.',
        'reviewed' => 'Marcado como revisado.',
        'lifted' => 'Suspensión levantada: :business vuelve a atender.',
        'reviewed_by' => 'Revisó :name',
    ],
];
