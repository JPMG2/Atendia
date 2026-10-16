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
    'staff_idle' => 'Cerramos tu sesión por inactividad. Ingresa de nuevo para seguir.',

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

    // What a new password has to be, shown while it is typed (register and reset).
    'password_rules' => [
        'weak' => 'Débil',
        'good' => 'Buena',
        'strong' => 'Fuerte',
        'length' => '8 caracteres o más',
        'upper' => 'Una letra mayúscula',
        'number' => 'Un número',
        'symbol' => 'Un carácter especial (ej. ! @ #)',
    ],

    // The brand panel beside the login and register forms (desktop only).
    'aside' => [
        'eyebrow' => 'Tu negocio, atendido por IA',
        'title' => 'Automatiza tu WhatsApp y no pierdas más clientes.',
        'sub' => 'Tu asistente responde, agenda turnos y muestra tus productos. Día y noche, en segundos.',
        'points' => [
            'fast' => 'Responde en 2 segundos, las 24 horas',
            'schedule' => 'Agenda turnos sin que muevas un dedo',
            'catalog' => 'Muestra tu catálogo dentro del chat',
        ],
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
        'heading' => 'Recupera tu contraseña',
        'intro' => '¿Olvidaste tu contraseña? No pasa nada: escribe tu email y te mandamos un enlace para elegir una nueva.',
        'submit' => 'Enviar el enlace',
    ],

    'reset' => [
        'heading' => 'Elige una contraseña nueva',
        'submit' => 'Restablecer la contraseña',
    ],

    'confirm' => [
        'heading' => 'Confirma tu contraseña',
        'intro' => 'Esta es una zona protegida. Confirma tu contraseña para seguir.',
        'submit' => 'Confirmar',
    ],

    'verify' => [
        'heading' => 'Confirma tu email',
        'intro' => 'Gracias por crear tu cuenta. Antes de empezar, confirma tu email con el enlace que acabamos de enviarte. Si no te llegó, te mandamos otro.',
        'sent' => 'Te enviamos un enlace nuevo al email con el que te registraste.',
        'resend' => 'Reenviar el correo de verificación',
        'logout' => 'Cerrar sesión',
    ],
];
