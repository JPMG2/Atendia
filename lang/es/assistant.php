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
        'owner_alert' => "🔔 *:name* (:phone) necesita a alguien del equipo.\nMotivo: :reason\nRespóndele desde tu WhatsApp; tu asistente quedó en pausa solo en ese chat.",
        'no_reason' => 'el cliente pidió hablar con una persona',
    ],
];
