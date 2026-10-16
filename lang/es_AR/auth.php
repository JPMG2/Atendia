<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Acceso — solo los overrides de voseo
|--------------------------------------------------------------------------
| Lo que no está acá cae a lang/es (tuteo neutro). Los archivos PHP SÍ hacen
| fallback por clave; el JSON no, y por eso esta copy vive acá.
*/

return [
    'staff_idle' => 'Cerramos tu sesión por inactividad. Ingresá de nuevo para seguir.',

    'aside' => [
        'title' => 'Automatizá tu WhatsApp y no pierdas más clientes.',
    ],

    'register' => [
        'heading' => 'Creá tu cuenta',
        'email_placeholder' => 'vos@tunegocio.com',
    ],

    'login' => [
        'sub' => 'Ingresá para seguir atendiendo con tu asistente.',
        'no_account' => '¿Todavía no tenés cuenta?',
        'register_cta' => 'Empezá gratis',
    ],

    'forgot' => [
        'heading' => 'Recuperá tu contraseña',
        'intro' => '¿Olvidaste tu contraseña? No pasa nada: escribí tu email y te mandamos un enlace para elegir una nueva.',
    ],

    'reset' => [
        'heading' => 'Elegí una contraseña nueva',
    ],

    'confirm' => [
        'heading' => 'Confirmá tu contraseña',
        'intro' => 'Esta es una zona protegida. Confirmá tu contraseña para seguir.',
    ],

    'verify' => [
        'heading' => 'Confirmá tu email',
        'intro' => 'Gracias por crear tu cuenta. Antes de empezar, confirmá tu email con el enlace que acabamos de enviarte. Si no te llegó, te mandamos otro.',
    ],
];
