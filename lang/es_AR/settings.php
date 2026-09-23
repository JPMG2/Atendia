<?php

declare(strict_types=1);

// Voseo overrides only: any key missing here falls back to lang/es/settings.php.
return [

    'email' => [
        'sub' => 'Con este correo entrás a Atendia y te llegan los avisos de seguridad. Para cambiarlo, confirmamos que el nuevo sea tuyo.',
        'pending' => 'Te mandamos un enlace a :email. Hasta que lo confirmes, seguís entrando con tu correo actual.',
        'cancelled' => 'Cancelaste el cambio: seguís entrando con tu correo actual.',
        'resend_throttled' => 'Ya te mandamos varios enlaces. Esperá :minutes min antes de pedir otro.',
    ],

    'password' => [
        'sub' => 'Usá al menos 8 caracteres, con una mayúscula, un número y un símbolo. Nunca te la vamos a pedir por WhatsApp.',
        'confirm' => 'Repetí la contraseña nueva',
    ],

    'two_factor' => [
        'sub' => 'Cuando alguien entre a tu cuenta desde un dispositivo nuevo, el código llega a tu WhatsApp en lugar de tu correo. Aunque se filtre tu correo, sin tu teléfono no entran.',
        'no_phone' => 'Para activarla, cargá tu WhatsApp personal en Mi negocio → Contacto.',
        'wrong_code' => 'Ese código no es o ya venció. Pedí uno nuevo si hace falta.',
        'send_throttled' => 'Ya te mandamos varios códigos. Esperá :minutes min antes de pedir otro.',
        'codes_warning' => 'Guardá estos códigos de respaldo en un lugar seguro: sirven para entrar si perdés el teléfono. Cada uno se usa una sola vez y no los vamos a volver a mostrar.',
        'codes_left' => '{0} No te quedan códigos de respaldo: generá nuevos.|{1} Te queda :count código de respaldo.|[2,*] Te quedan :count códigos de respaldo.',
        'send_failed' => 'WhatsApp no respondió y no pudimos mandarte el código. Probá de nuevo en unos minutos.',
    ],

    'close' => [
        'sub' => 'Se cierran tu acceso y tu negocio: tu asistente deja de atender. Guardamos todo durante :days días por si querés volver.',
        'sub_no_business' => 'Se cierra tu acceso. Guardamos todo durante :days días por si querés volver.',
        'confirmation' => 'Escribí :keyword para confirmar',
        'confirm_message' => 'Tu asistente deja de atender ahora mismo. Tenés :days días para volver con todo tal como estaba.',
    ],

    'links' => [
        'verified_body' => ':email ya está confirmado. Podés volver a Atendia desde cualquier dispositivo.',
        'confirmed_body' => 'Desde ahora entrás a Atendia con :email.',
        'invalid_body' => 'Venció, ya se usó o hubo un cambio más nuevo. Pedí otro desde tus ajustes.',
        'cancelled_body' => 'Tu correo de acceso sigue igual. Si no fuiste vos quien lo pidió, cambiá tu contraseña ahora.',
        'restored_body' => 'Restauramos tu cuenta y tu negocio tal como estaban. Iniciá sesión para seguir.',
        'restore_expired_body' => 'Pasó el plazo para volver solo. Escribinos y lo revisamos con vos.',
    ],

];
