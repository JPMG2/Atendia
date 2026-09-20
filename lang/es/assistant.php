<?php

declare(strict_types=1);

// Lo que el asistente responde por WhatsApp SIN pasar por la IA: los frenos
// del guardián. Español neutro; el espejo de idiomas es cosa del modelo.
return [
    'guard' => [
        'too_many' => 'Estamos recibiendo muchos mensajes tuyos seguidos. Hagamos una pausa y en un rato seguimos con gusto.',
        'offensive' => 'Estamos para ayudarte con el negocio. Sigamos la conversación con respeto y con gusto te atendemos.',
    ],

    // The end customer NEVER hears about the owner's plan: the gate closes
    // with a courteous ask for text, not a sales pitch that shames the shop.
    'plan' => [
        'audio' => '¿Me lo cuentas por mensaje de texto? Por aquí no puedo escuchar audios, y por escrito te ayudo enseguida.',
        'cap_warning' => '🤖 Tu asistente ya atendió :used de las :cap conversaciones que incluye tu plan este mes. Todo sigue funcionando con normalidad; si quieres más capacidad, revisa tu plan: :url',
    ],

    'digest' => [
        'header' => "🤖 Resumen del día — :business\nTu asistente atendió :messages mensajes de :contacts contactos.",
        'referrals' => '🎉 1 negocio llegó con tu enlace esta semana. ¡Sigue compartiéndolo!|🎉 :count negocios llegaron con tu enlace esta semana. ¡Sigue compartiéndolo!',
    ],

    'handoff' => [
        'owner_alert' => "🔔 *:name* (:phone) necesita a alguien del equipo.\nMotivo: :reason\nRespóndeme por aquí y le reenvío tu mensaje, o atiéndelo desde tu panel: :url\nTu asistente quedó en pausa solo en ese chat.",
        'owner_channel' => '🤖 Hola, soy tu asistente. Ahora no hay ninguna conversación esperando al equipo; las ves todas en tu panel: :url',
        'relay_done' => "✅ Le envié tu respuesta a *:name*.\nCuando el tema esté cerrado, escríbeme *#resuelto* y tu asistente retoma ese chat. También lo ves en tu panel: :url",
        'resolved_done' => '✔️ Listo: el hilo con *:name* quedó resuelto. Tu asistente lo atiende de nuevo si vuelve a escribir.',
        'no_reason' => 'el cliente pidió hablar con una persona',
        'reminder_owner' => '⏰ *:name* sigue esperando a tu equipo hace :minutes minutos. Le avisé que estás en camino.',
        'hold_customer' => '🤖 Seguimos con tu consulta: una persona del equipo de :business está algo demorada, apenas se libere te escribe. ¡Gracias por la paciencia!',
        'auto_resume' => '🤖 Retomo yo tu consulta mientras el equipo se desocupa. ¿En qué te puedo ayudar?',
    ],
];
