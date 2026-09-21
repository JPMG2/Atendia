<?php

declare(strict_types=1);

// Solo overrides de voseo; lo que no está cae a lang/es/assistant.php.
return [
    'plan' => [
        'audio' => '¿Me lo contás por mensaje de texto? Por acá no puedo escuchar audios, y por escrito te ayudo enseguida.',
    ],

    'digest' => [
        'knowledge_footer' => 'Enseñale las respuestas desde tu panel y no vuelve a pasar: :url',
    ],

    'handoff' => [
        'owner_alert' => "🔔 *:name* (:phone) necesita a alguien del equipo.\nMotivo: :reason\nRespondeme por acá y le reenvío tu mensaje, o atendelo desde tu panel: :url\nTu asistente quedó en pausa solo en ese chat.",
        'relay_done' => "✅ Le envié tu respuesta a *:name*.\nCuando el tema esté cerrado, escribime *#resuelto* y tu asistente retoma ese chat. También lo ves en tu panel: :url",
    ],
];
