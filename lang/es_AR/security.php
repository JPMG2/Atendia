<?php

declare(strict_types=1);

// Voseo overrides only: any key missing here falls back to lang/es/security.php.
return [

    'challenge' => [
        'title' => 'Revisá tu correo',
        'sub' => 'Este dispositivo es nuevo para tu cuenta: te enviamos un código de 6 dígitos para confirmar que sos vos.',
        'hint' => 'El código vence en 10 minutos. Si no llega, revisá el correo no deseado.',
        'wrong_code' => 'Ese código no es. Revisá el correo e intentá de nuevo.',
        'use_recovery' => '¿Sin acceso a tu WhatsApp? Usá un código de respaldo',
    ],

    'challenge_whatsapp' => [
        'title' => 'Revisá tu WhatsApp',
        'sub' => 'Este dispositivo es nuevo para tu cuenta: te enviamos un código de 6 dígitos por WhatsApp para confirmar que sos vos.',
        'wrong_code' => 'Ese código no es. Revisá tu WhatsApp e intentá de nuevo.',
    ],

    'whatsapp_code' => 'Tu código de Atendia es *:code*. Vence en 10 minutos. Si no fuiste vos, no lo compartas con nadie.',

    'revoked' => [
        'body' => 'Cerramos esa sesión: para volver a entrar va a necesitar la contraseña. Si no reconocías ese acceso, cambiá tu contraseña ahora.',
    ],

];
