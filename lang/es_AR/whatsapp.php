<?php

declare(strict_types=1);

// Solo los overrides de voseo: lo que no está acá cae a lang/es/whatsapp.php.
return [
    'sub' => 'Conectá el número que atiende tu asistente.',
    'no_business' => 'Primero creá tu negocio y después conectamos tu WhatsApp.',

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
    ],
];
