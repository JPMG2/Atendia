<?php

declare(strict_types=1);

// Solo overrides de voseo; lo que no está cae a lang/es/assistant.php.
return [
    'plan' => [
        'voice_note_placeholder' => '🎤 Nota de voz (tu plan no incluye audios: escuchala en tu WhatsApp)',
        'audio' => '¿Me lo contás por mensaje de texto? Por acá no puedo escuchar audios, y por escrito te ayudo enseguida.',
        'cap_warning' => '🤖 Tu asistente ya atendió :used de las :cap conversaciones que incluye tu plan este mes. Todo sigue funcionando con normalidad; si querés más capacidad, revisá tu plan: :url',
    ],

    'media' => [
        'on_phone' => 'El archivo no llegó al panel: abrilo en tu WhatsApp',
    ],

    'digest' => [
        'knowledge_footer' => 'Enseñale las respuestas desde tu panel y no vuelve a pasar: :url',
    ],

    'handoff' => [
        'pick_thread' => "❓ Tenés más de una conversación esperando. ¿Para quién es tu mensaje? Respondeme solo con el número:\n:list\n\nConsejo: si respondés citando mi aviso (mantené apretado → Responder), te lo reenvío directo.",
        'department_alert' => "🔔 *:name* (:phone) necesita a *:department*.\nMotivo: :reason\nRespondeme por acá y le reenvío tu mensaje, o atendelo desde tu panel: :url",
        'owner_alert' => "🔔 *:name* (:phone) necesita a alguien del equipo.\nMotivo: :reason\nRespondeme por acá y le reenvío tu mensaje, o atendelo desde tu panel: :url\nTu asistente quedó en pausa solo en ese chat.",
        'relay_done' => "✅ Le envié tu respuesta a *:name*.\nCuando el tema esté cerrado, escribime *#resuelto* y tu asistente retoma ese chat. También lo ves en tu panel: :url",
    ],
];
