<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Mensajes de autenticación
|--------------------------------------------------------------------------
*/

return [
    'failed' => 'Estas credenciales no coinciden con nuestros registros.',
    'password' => 'La contraseña es incorrecta.',
    'throttle' => 'Demasiados intentos de acceso. Vuelve a intentar en :seconds segundos.',

    /*
    |----------------------------------------------------------------------
    | Texto de las pantallas de acceso
    |----------------------------------------------------------------------
    | Acá y no en lang/es.json: las traducciones JSON NO tienen fallback
    | (Translator::get las busca solo en el locale pedido), así que un
    | visitante con es_AR o es_VE veía las claves en inglés.
    */

    'fields' => [
        'name' => 'Nombre',
        'email' => 'Email',
        'password' => 'Contraseña',
        'password_confirmation' => 'Confirmar la contraseña',
    ],

    'login' => [
        'heading' => 'Hola de nuevo',
        'sub' => 'Ingresa para seguir atendiendo con tu asistente.',
        'remember' => 'Mantener la sesión iniciada',
        'forgot' => '¿Olvidaste tu contraseña?',
        'submit' => 'Ingresar',
        'no_account' => '¿Todavía no tienes cuenta?',
        'register_cta' => 'Empieza gratis',
    ],

    'register' => [
        'heading' => 'Crea tu cuenta',
        'sub' => 'Tres datos y tu asistente empieza a tomar forma.',
        'name_placeholder' => 'María Gómez',
        'email_placeholder' => 'tu@tunegocio.com',
        'password_placeholder' => 'Ej. MiClave#2026',
        'confirm_placeholder' => 'La misma contraseña, para estar seguros',
        'already' => '¿Ya creaste tu cuenta?',
        'submit' => 'Crear mi cuenta',
    ],

    'forgot' => [
        'intro' => '¿Olvidaste tu contraseña? No pasa nada: escribe tu email y te mandamos un enlace para elegir una nueva.',
        'submit' => 'Enviar el enlace',
    ],

    'reset' => [
        'submit' => 'Restablecer la contraseña',
    ],

    'confirm' => [
        'intro' => 'Esta es una zona protegida. Confirma tu contraseña para seguir.',
        'submit' => 'Confirmar',
    ],

    'verify' => [
        'intro' => 'Gracias por crear tu cuenta. Antes de empezar, confirma tu email con el enlace que acabamos de enviarte. Si no te llegó, te mandamos otro.',
        'sent' => 'Te enviamos un enlace nuevo al email con el que te registraste.',
        'resend' => 'Reenviar el correo de verificación',
        'logout' => 'Cerrar sesión',
    ],
];
