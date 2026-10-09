<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Seguridad — copy de las pantallas públicas de seguridad
|--------------------------------------------------------------------------
|
| Base NEUTRA (tuteo). Los verbos en segunda persona tienen override de
| voseo en `lang/es_AR/security.php`.
|
*/

return [

    // The platform's own staff: the page where they turn the second step on.
    'staff' => [
        'title' => 'Mi seguridad',
        'sub' => 'El doble factor de tu cuenta del equipo: el código de cada ingreso desde un dispositivo nuevo te llega por WhatsApp.',
        'required' => 'Venció el plazo para activar el doble factor. Actívalo para volver a usar el panel.',
        'banner' => '{0} Hoy vence el plazo para activar el doble factor.|{1} Queda :count día para activar el doble factor.|[2,*] Quedan :count días para activar el doble factor.',
        'banner_cta' => 'Activarlo',
        'overdue_title' => 'El plazo venció',
        'overdue_body' => 'Hasta que lo actives, esta es la única pantalla del panel que puedes abrir.',
        'reset_title' => 'Te restablecieron el doble factor',
        'reset_body' => 'Quien administra la plataforma lo restableció: vuelve a activarlo para seguir usando el panel.',
        'phone' => [
            'title' => 'Tu WhatsApp',
            'hint' => 'A este número te llega el código. Solo dígitos, con el código del país.',
            'field' => 'Número de WhatsApp',
            'save' => 'Guardar el número',
            'saved' => 'Número guardado.',
            'invalid' => 'Escribe un número con código de país, solo dígitos (de 8 a 15).',
        ],
    ],

    'challenge' => [
        'title' => 'Revisa tu correo',
        'sub' => 'Este dispositivo es nuevo para tu cuenta: te enviamos un código de 6 dígitos para confirmar que eres tú.',
        'label' => 'Código de 6 dígitos',
        'cta' => 'Confirmar y entrar',
        'back' => 'Volver a iniciar sesión',
        'hint' => 'El código vence en 10 minutos. Si no llega, revisa el correo no deseado.',
        'wrong_code' => 'Ese código no es. Revisa el correo e intenta de nuevo.',
        'sent_to' => 'Lo enviamos a',
        'resend' => 'Reenviar el código',
        'resent' => 'Listo. Te enviamos un código nuevo.',
        'use_recovery' => '¿Sin acceso a tu WhatsApp? Usa un código de respaldo',
        'recovery_label' => 'Código de respaldo',
        'recovery_cta' => 'Entrar con el código de respaldo',
        'wrong_recovery' => 'Ese código de respaldo no es válido o ya se usó.',
        'whatsapp_failed' => 'WhatsApp no respondió, así que esta vez te mandamos el código por correo.',
    ],

    // Login codes that go by WhatsApp once two-step verification is on.
    'challenge_whatsapp' => [
        'title' => 'Revisa tu WhatsApp',
        'sub' => 'Este dispositivo es nuevo para tu cuenta: te enviamos un código de 6 dígitos por WhatsApp para confirmar que eres tú.',
        'hint' => 'El código vence en 10 minutos.',
        'wrong_code' => 'Ese código no es. Revisa tu WhatsApp e intenta de nuevo.',
    ],

    'whatsapp_code' => 'Tu código de :brand es *:code*. Vence en 10 minutos. Si no fuiste tú, no lo compartas con nadie.',

    'revoked' => [
        'title' => 'Ese dispositivo quedó fuera',
        'body' => 'Cerramos esa sesión: para volver a entrar va a necesitar la contraseña. Si no reconocías ese acceso, cambia tu contraseña ahora.',
        'cta' => 'Cambiar mi contraseña',
        'back' => 'Ir a iniciar sesión',
    ],

];
