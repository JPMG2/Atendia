<?php

declare(strict_types=1);

// Solo overrides de voseo; lo que no está cae a lang/es/assistant.php.
return [
    'plan' => [
        'audio' => '¿Me lo contás por mensaje de texto? Por acá no puedo escuchar audios, y por escrito te ayudo enseguida.',
    ],

    'handoff' => [
        'owner_alert' => "🔔 *:name* (:phone) necesita a alguien del equipo.\nMotivo: :reason\nRespondele desde tu WhatsApp; tu asistente quedó en pausa solo en ese chat.",
    ],
];
