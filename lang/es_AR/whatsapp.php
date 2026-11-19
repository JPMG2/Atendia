<?php

declare(strict_types=1);

// Solo los overrides de voseo: lo que no está acá cae a lang/es/whatsapp.php.
return [
    'sub' => 'Conectá el número que atiende tu asistente.',
    'no_business' => 'Primero creá tu negocio y después conectamos tu WhatsApp.',

    'connected' => [
        'identity_hint' => 'Este es el número que quedó vinculado. Si no es el de tu negocio, desconectalo y vinculá el correcto.',
    ],

    'disconnect' => [
        'done' => 'Listo. El número quedó desconectado; vinculá el correcto con el código QR.',
        'failed' => 'No pudimos desconectar el número ahora. Probá de nuevo en un momento.',
    ],

    'lost_phone' => [
        'body' => 'Pausá tu WhatsApp y nadie puede usarlo a través de :brand hasta que lo reactives con tu contraseña nueva. No es dar de baja: no se borra nada.',
        'effects' => [
            'Tu asistente deja de responder y no sale ningún mensaje ni recordatorio por tu número.',
            'El número se desvincula: cuando lo recuperes, lo volvés a vincular con el código QR.',
            'Se cierran todas las sesiones y la app, también las de tu equipo.',
            'Los avisos y los códigos de acceso dejan de ir a tu WhatsApp: te llegan por correo.',
        ],
        'password_hint' => 'Elegí una contraseña nueva: la que quedó guardada en el teléfono deja de servir. Si tu correo también estaba abierto en ese teléfono, cambiale la contraseña desde su propia página.',
        'paused_not_unlinked' => 'Tu WhatsApp quedó en pausa y nada sale por tu número, pero no pudimos desvincularlo ahora. Quitalo también desde Dispositivos vinculados cuando recuperes tu número.',
        'resume_body' => 'Escribí la contraseña que elegiste al pausar para reactivarlo. Después escaneá el código QR desde el teléfono con tu número y tu asistente vuelve a atender.',
        'resumed' => 'Listo. Ahora escaneá el código QR para que tu asistente vuelva a atender.',
        'cut_hint' => 'Quedaron fuera al pausar: la app y los navegadores de tu equipo que entraron en los últimos :days días. Tu contraseña ya cambió; si no reconocés un dispositivo de alguien de tu equipo, pedile que cambie la suya.',
        'app_resume' => 'Para reactivarlo, entrá a la web, en WhatsApp, con la contraseña nueva.',
    ],

    'connect' => [
        'title' => 'Conectá tu WhatsApp',
        'body' => 'Vinculá el número de tu negocio y tu asistente empieza a responder por vos, las 24 horas.',
        'steps' => [
            'Abrí WhatsApp en tu teléfono',
            'Tocá Dispositivos vinculados y luego Vincular un dispositivo',
            'Escaneá el código de esta pantalla',
        ],
        'qr_hint' => 'El código se renueva solo. No cierres esta pantalla.',
        'done' => 'Listo. Tu asistente ya responde por vos.',
        'failed' => 'No pudimos conectar con WhatsApp. Intentá de nuevo en un momento.',
        'dedicated_title' => 'Usá un número exclusivo para tu negocio',
        'tip' => '¿Tu número es nuevo? WhatsApp confía más en los números con historia: usalo unos días de forma normal antes de conectarlo y arrancás con la mejor reputación.',
        'dedicated_body' => 'Si conectás tu número personal, el asistente también les va a responder a tus familiares y amigos, y tus chats privados se van a mezclar con los de tus clientes.',
    ],

    'business_app' => [
        'line' => 'Si usás WhatsApp Business, apagá el mensaje de saludo y el de ausencia: pueden salir a la vez que el asistente.',
        'reminder' => 'Si usás WhatsApp Business, revisá que el saludo y la ausencia de la app estén apagados.',
        'steps' => [
            'Abrí WhatsApp Business y entrá a Herramientas para la empresa.',
            'Abrí Mensaje de saludo y desactivalo.',
            'Hacé lo mismo con Mensaje de ausencia.',
        ],
    ],
];
