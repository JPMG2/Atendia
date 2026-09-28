<?php

declare(strict_types=1);

// Solo overrides de voseo; lo que no está cae a lang/es/moderation.php.
return [
    'upload' => [
        'rejected' => 'No podemos usar esta imagen: parece tener contenido para adultos. Elegí otra.',
        'unavailable' => 'No pudimos revisar la imagen. Intentalo de nuevo en unos minutos.',
    ],

    'appeal' => [
        'open' => '¿Creés que es un error? Apelar',
        'field' => 'Contanos por qué',
    ],

    'mail' => [
        'body' => 'Tu asistente dejó de responder mientras lo revisamos. No se borró nada. Si creés que es un error, apelá desde tu panel y lo revisamos.',
        'reason' => 'Recibiste este correo porque sos parte del equipo de este negocio en AtendIa.',
    ],

    'whatsapp' => '⚠️ *AtendIa*: suspendimos el asistente de *:name* por contenido que incumple nuestras políticas de uso. No se borró nada. Si creés que es un error, apelá desde tu panel.',
];
