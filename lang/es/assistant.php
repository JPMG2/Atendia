<?php

declare(strict_types=1);

// Lo que el asistente responde por WhatsApp SIN pasar por la IA: los frenos
// del guardián. Español neutro; el espejo de idiomas es cosa del modelo.
return [
    'guard' => [
        'too_many' => 'Estamos recibiendo muchos mensajes tuyos seguidos. Hagamos una pausa y en un rato seguimos con gusto.',
        'offensive' => 'Estamos para ayudarte con el negocio. Sigamos la conversación con respeto y con gusto te atendemos.',
    ],

    'digest' => [
        'header' => "🤖 Resumen del día — :business\nTu asistente atendió :messages mensajes de :contacts contactos.",
    ],
];
