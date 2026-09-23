<?php

declare(strict_types=1);

// Voseo overrides only: any key missing here falls back to lang/es/mail.php.
return [

    'business_welcome' => [
        'preheader' => 'Tu asistente ya sabe presentarse. Conectá tu WhatsApp y empezá a atender.',
        'next' => 'Un solo paso lo separa de atender de verdad: conectá el WhatsApp de tu negocio.',
    ],

    'contact_updated' => [
        'alert' => 'Si no hiciste este cambio, entrá a tu panel y revisá tus datos de contacto.',
    ],

    'referral_link' => [
        'preheader' => 'Compartilo con otros negocios y ganá descuentos en tu factura.',
        'qr_hint' => 'Imprimí este código y pegalo en tu mostrador: quien lo escanea llega con tu enlace.',
        'title' => 'Este enlace es tuyo: compartilo y ganá',
        'body' => 'Reenviá este correo o compartí el enlace donde quieras: quien se registre con él estrena Atendia con :days días de prueba gratis, y cuando pague su primer mes vos ganás un :percent% de descuento en tu próxima factura. Los descuentos se acumulan.',
    ],

    'challenge' => [
        'alert' => 'Si no intentaste entrar, no compartas este código con nadie y cambiá tu contraseña.',
    ],

    'new_device' => [
        'body_ok' => 'Si fuiste vos, no hay nada que hacer: vamos a recordar este dispositivo.',
        'body_alert' => 'Si no reconocés este acceso, cambiá tu contraseña ahora mismo.',
    ],

    'account' => [
        'password_reset' => [
            'subject' => 'Restablecé tu contraseña de Atendia',
            'preheader' => 'Un clic y elegís una contraseña nueva.',
            'title' => 'Elegí una contraseña nueva',
            'body' => 'Tocá el botón para elegir una nueva. El enlace vence en :minutes minutos.',
            'alert' => 'Si no lo pediste, ignorá este correo: tu contraseña sigue igual.',
        ],
        'two_factor_alert' => 'Si no fuiste vos, cambiá tu contraseña ahora mismo.',
        'two_factor_on' => [
            'body' => 'Cuando alguien entre desde un dispositivo nuevo, el código va a llegar a tu WhatsApp. Guardá tus códigos de respaldo por si perdés el teléfono.',
        ],
        'verify' => [
            'subject' => 'Verificá tu correo de Atendia',
            'title' => 'Verificá tu correo',
            'intro' => 'Hola :name, confirmá que esta dirección es tuya:',
            'closing' => 'Así nos aseguramos de que tu cuenta llegue solo a vos.',
        ],
        'email_change' => [
            'subject' => 'Confirmá tu nuevo correo de acceso',
            'title' => 'Confirmá tu nuevo correo',
            'body' => 'Tocá el botón para confirmarla. El enlace vence en :minutes minutos y, hasta que lo uses, seguís entrando con tu correo actual.',
            'alert' => 'Si no pediste este cambio, ignorá este correo: nada va a cambiar.',
            'closing' => 'Así nos aseguramos de que tu cuenta llegue solo a vos.',
        ],
        'email_notice' => [
            'preheader' => 'Si fuiste vos, no hay nada que hacer. Si no, frenalo con un clic.',
            'body' => 'Si fuiste vos, no hay nada que hacer: el cambio se completa cuando confirmes desde el nuevo correo.',
            'alert' => 'Si no fuiste vos, cancelá el cambio ahora y cambiá tu contraseña.',
        ],
        'email_updated' => [
            'preheader' => 'Desde ahora entrás a Atendia con esta dirección.',
            'alert' => 'Si no hiciste este cambio, entrá a tu panel y revisá tus ajustes.',
        ],
        'password_changed' => [
            'preheader' => 'Si fuiste vos, no hay nada que hacer.',
            'body' => 'Si fuiste vos, no hay nada que hacer.',
            'alert' => 'Si no fuiste vos, restablecé tu contraseña ahora mismo: vas a cerrar cualquier acceso que no reconozcas.',
        ],
        'closed' => [
            'preheader' => 'Tenés :days días para volver con todo tal como estaba.',
            'body' => 'Guardamos todo tal como estaba durante :days días: si cambiás de idea, volvés con un clic o simplemente iniciando sesión.',
            'alert' => 'Si no fuiste vos, restaurá tu cuenta ahora y cambiá tu contraseña.',
        ],
    ],

];
